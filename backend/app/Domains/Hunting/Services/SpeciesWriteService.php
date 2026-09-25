<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Enums\ConservationAssessmentScope;
use App\Domains\Hunting\Enums\HabitatImportance;
use App\Domains\Hunting\Enums\IdentificationTraitCategory;
use App\Domains\Hunting\Enums\NativeStatus;
use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Hunting\Enums\SpeciesContentStatus;
use App\Domains\Hunting\Enums\SpeciesDomainType;
use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Events\SpeciesChanged;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\Habitat;
use App\Domains\Hunting\Models\KnowledgeCitation;
use App\Domains\Hunting\Models\KnowledgeSource;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesConservationAssessment;
use App\Domains\Hunting\Models\SpeciesHabitat;
use App\Domains\Hunting\Models\SpeciesIdentificationTrait;
use App\Domains\Hunting\Models\SpeciesTranslation;
use App\Domains\Hunting\Support\SpeciesHtmlSanitizer;
use App\Domains\Hunting\Support\SpeciesLocales;
use App\Domains\Hunting\Support\SpeciesLogger;
use App\Domains\Hunting\Support\SpeciesSlug;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SpeciesWriteService
{
    public function __construct(
        private readonly TaxonomyService $taxonomy,
        private readonly SpeciesHtmlSanitizer $sanitizer,
        private readonly SpeciesCharacteristicService $characteristics,
        private readonly SpeciesRevisionService $revisions,
        private readonly SpeciesLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, User $actor): Species
    {
        $taxonomy = $this->taxonomy->normalize($input);

        $this->assertScientificNameUnique($taxonomy['scientific_name_normalized']);

        $slug = isset($input['canonical_slug']) && is_string($input['canonical_slug']) && $input['canonical_slug'] !== ''
            ? SpeciesSlug::normalize($input['canonical_slug'])
            : SpeciesSlug::fromScientificName($taxonomy['scientific_name']);

        if (! SpeciesSlug::isValid($slug) || Species::withTrashed()->where('canonical_slug', $slug)->exists()) {
            throw SpeciesException::slugConflict();
        }

        return DB::transaction(function () use ($input, $actor, $taxonomy, $slug): Species {
            $species = Species::query()->create([
                ...$taxonomy,
                'public_id' => (string) Str::uuid(),
                'canonical_slug' => $slug,
                'domain_type' => SpeciesDomainType::from((string) $input['domain_type']),
                'activity_type' => SpeciesActivityType::from((string) $input['activity_type']),
                'native_status' => isset($input['native_status']) && $input['native_status']
                    ? NativeStatus::from((string) $input['native_status'])
                    : null,
                'verification_status' => SpeciesVerificationStatus::from(
                    (string) ($input['verification_status'] ?? SpeciesVerificationStatus::Unverified->value),
                ),
                'publication_status' => SpeciesPublicationStatus::Draft,
                'content_version' => 1,
                'no_media_required' => (bool) ($input['no_media_required'] ?? false),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncNested($species, $input, $actor, 'Created species draft.');
            $this->logger->info('created', ['species_public_id' => $species->public_id]);
            event(new SpeciesChanged($species->id, $species->public_id, 'created'));

            return $species->fresh() ?? $species;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Species $species, array $input, User $actor): Species
    {
        $expected = (int) ($input['content_version'] ?? 0);
        if ($expected !== $species->content_version) {
            throw SpeciesException::versionConflict([
                'content_version' => $species->content_version,
            ]);
        }

        if ($species->publication_status === SpeciesPublicationStatus::Archived
            && ! array_key_exists('canonical_slug', $input)) {
            // archived records remain editable by admins; slug stays stable
        }

        return DB::transaction(function () use ($species, $input, $actor): Species {
            $locked = Species::query()->whereKey($species->id)->lockForUpdate()->firstOrFail();

            if (isset($input['scientific_name']) || isset($input['kingdom']) || isset($input['taxonomic_rank'])) {
                $taxonomy = $this->taxonomy->normalize([
                    ...$locked->only([
                        'scientific_name',
                        'taxonomic_rank',
                        'kingdom',
                        'phylum',
                        'class_name',
                        'order_name',
                        'family',
                        'genus',
                        'species_epithet',
                        'scientific_name_authorship',
                    ]),
                    ...array_filter(
                        $input,
                        static fn (mixed $value): bool => $value !== null,
                    ),
                ]);
                $this->assertScientificNameUnique($taxonomy['scientific_name_normalized'], $locked->id);
                $locked->fill($taxonomy);
            }

            if (isset($input['domain_type'])) {
                $locked->domain_type = SpeciesDomainType::from((string) $input['domain_type']);
            }
            if (isset($input['activity_type'])) {
                $locked->activity_type = SpeciesActivityType::from((string) $input['activity_type']);
            }
            if (array_key_exists('native_status', $input)) {
                $locked->native_status = $input['native_status']
                    ? NativeStatus::from((string) $input['native_status'])
                    : null;
            }
            if (isset($input['verification_status'])) {
                $locked->verification_status = SpeciesVerificationStatus::from((string) $input['verification_status']);
            }
            if (array_key_exists('no_media_required', $input)) {
                $locked->no_media_required = (bool) $input['no_media_required'];
            }

            $locked->updated_by = $actor->id;
            $locked->content_version = $locked->content_version + 1;
            $locked->save();

            $this->syncNested($locked, $input, $actor, (string) ($input['change_summary'] ?? 'Updated species.'));
            event(new SpeciesChanged($locked->id, $locked->public_id, 'updated'));

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function syncNested(Species $species, array $input, User $actor, string $summary): void
    {
        if (isset($input['translations']) && is_array($input['translations'])) {
            $this->syncTranslations($species, $input['translations']);
        }

        if (isset($input['characteristics']) && is_array($input['characteristics'])) {
            $this->characteristics->sync($species, $input['characteristics']);
        }

        if (isset($input['habitats']) && is_array($input['habitats'])) {
            $this->syncHabitats($species, $input['habitats']);
        }

        if (isset($input['identification_traits']) && is_array($input['identification_traits'])) {
            $this->syncTraits($species, $input['identification_traits']);
        }

        if (isset($input['conservation']) && is_array($input['conservation'])) {
            $this->syncConservation($species, $input['conservation']);
        }

        if (isset($input['citation_source_ids']) && is_array($input['citation_source_ids'])) {
            $this->syncCitations($species, $input['citation_source_ids']);
        }

        $this->revisions->record($species->fresh() ?? $species, $actor, $summary);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncTranslations(Species $species, array $rows): void
    {
        foreach ($rows as $row) {
            $locale = (string) ($row['locale'] ?? '');
            if (! SpeciesLocales::isSupported($locale)) {
                continue;
            }

            foreach (['summary', 'identification', 'appearance', 'behavior', 'diet', 'habitat_description', 'breeding_notes', 'seasonal_behavior', 'field_notes', 'safety_notes'] as $field) {
                if (isset($row[$field])) {
                    $row[$field] = $this->sanitizer->sanitize(is_string($row[$field]) ? $row[$field] : null);
                }
            }

            SpeciesTranslation::query()->updateOrCreate(
                ['species_id' => $species->id, 'locale' => $locale],
                [
                    'common_name' => mb_substr(trim((string) ($row['common_name'] ?? '')), 0, 120),
                    'short_name' => $this->nullable($row['short_name'] ?? null, 80),
                    'summary' => (string) ($row['summary'] ?? ''),
                    'identification' => (string) ($row['identification'] ?? ''),
                    'appearance' => $this->nullable($row['appearance'] ?? null),
                    'behavior' => $this->nullable($row['behavior'] ?? null),
                    'diet' => $this->nullable($row['diet'] ?? null),
                    'habitat_description' => $this->nullable($row['habitat_description'] ?? null),
                    'breeding_notes' => $this->nullable($row['breeding_notes'] ?? null),
                    'seasonal_behavior' => $this->nullable($row['seasonal_behavior'] ?? null),
                    'field_notes' => $this->nullable($row['field_notes'] ?? null),
                    'safety_notes' => $this->nullable($row['safety_notes'] ?? null),
                    'seo_title' => $this->nullable($row['seo_title'] ?? null, 70),
                    'seo_description' => $this->nullable($row['seo_description'] ?? null, 170),
                    'content_status' => SpeciesContentStatus::from(
                        (string) ($row['content_status'] ?? SpeciesContentStatus::Draft->value),
                    ),
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncHabitats(Species $species, array $rows): void
    {
        SpeciesHabitat::query()->where('species_id', $species->id)->delete();
        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            $habitat = Habitat::query()->where('code', $code)->where('is_active', true)->first();
            if ($habitat === null) {
                continue;
            }
            SpeciesHabitat::query()->create([
                'species_id' => $species->id,
                'habitat_id' => $habitat->id,
                'importance' => HabitatImportance::from((string) ($row['importance'] ?? HabitatImportance::Secondary->value)),
                'notes' => $this->nullable($row['notes'] ?? null, 500),
                'source_id' => KnowledgeSource::resolveKey($row['source_id'] ?? $row['source_public_id'] ?? null),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncTraits(Species $species, array $rows): void
    {
        SpeciesIdentificationTrait::query()->where('species_id', $species->id)->delete();
        foreach ($rows as $index => $row) {
            $locale = (string) ($row['locale'] ?? SpeciesLocales::default());
            if (! SpeciesLocales::isSupported($locale)) {
                continue;
            }
            $description = $this->sanitizer->sanitize((string) ($row['description'] ?? ''));
            SpeciesIdentificationTrait::query()->create([
                'species_id' => $species->id,
                'locale' => $locale,
                'category' => IdentificationTraitCategory::from((string) ($row['category'] ?? IdentificationTraitCategory::Markings->value)),
                'label' => mb_substr(trim((string) ($row['label'] ?? '')), 0, 120),
                'description' => (string) $description,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
                'source_id' => KnowledgeSource::resolveKey($row['source_id'] ?? $row['source_public_id'] ?? null),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncConservation(Species $species, array $rows): void
    {
        SpeciesConservationAssessment::query()->where('species_id', $species->id)->delete();
        foreach ($rows as $row) {
            $sourceId = KnowledgeSource::resolveKey($row['source_id'] ?? $row['source_public_id'] ?? null);
            if ($sourceId === null) {
                throw SpeciesException::sourceRequired();
            }
            SpeciesConservationAssessment::query()->create([
                'species_id' => $species->id,
                'assessment_system' => mb_substr((string) ($row['assessment_system'] ?? ''), 0, 64),
                'status_code' => mb_substr((string) ($row['status_code'] ?? ''), 0, 64),
                'assessment_scope' => ConservationAssessmentScope::from((string) ($row['assessment_scope'] ?? ConservationAssessmentScope::Global->value)),
                'assessed_at' => $row['assessed_at'] ?? null,
                'source_id' => $sourceId,
                'notes' => $this->nullable($row['notes'] ?? null),
            ]);
        }
    }

    /**
     * @param  list<int|string>  $sourceIds
     */
    private function syncCitations(Species $species, array $sourceIds): void
    {
        KnowledgeCitation::query()
            ->where('citable_type', 'species')
            ->where('citable_id', $species->id)
            ->delete();

        foreach (array_unique(array_map('intval', $sourceIds)) as $sourceId) {
            if ($sourceId < 1 || KnowledgeSource::query()->whereKey($sourceId)->doesntExist()) {
                continue;
            }
            KnowledgeCitation::query()->create([
                'source_id' => $sourceId,
                'citable_type' => 'species',
                'citable_id' => $species->id,
                'claim_key' => 'species',
            ]);
        }
    }

    private function assertScientificNameUnique(string $normalized, ?int $ignoreId = null): void
    {
        $query = Species::withTrashed()->where('scientific_name_normalized', $normalized);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw SpeciesException::scientificNameConflict();
        }
    }

    private function nullable(mixed $value, ?int $max = null): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $max !== null ? mb_substr($trimmed, 0, $max) : $trimmed;
    }
}
