<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Models\KnowledgeCitation;
use App\Domains\Hunting\Models\KnowledgeSource;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesAlias;
use App\Domains\Hunting\Models\SpeciesConservationAssessment;
use App\Domains\Hunting\Models\SpeciesHabitat;
use App\Domains\Hunting\Models\SpeciesIdentificationTrait;
use App\Domains\Hunting\Models\SpeciesMediaAttribution;
use App\Domains\Hunting\Models\SpeciesSimilar;
use App\Domains\Hunting\Models\SpeciesTranslation;
use App\Domains\Hunting\Support\SpeciesLocales;
use App\Domains\Hunting\Support\SpeciesMediaUrl;

final class SpeciesPresenter
{
    public function __construct(private readonly SpeciesTranslationResolver $resolver) {}

    /**
     * @return array<string, mixed>
     */
    public function card(Species $species, string $locale): array
    {
        $translation = $this->resolver->resolve($species, $locale, true);
        $primary = $this->primaryMedia($species, $locale);

        return [
            'id' => $species->public_id,
            'slug' => $species->canonical_slug,
            'locale' => $translation instanceof SpeciesTranslation
                ? $translation->locale
                : $locale,
            'common_name' => $translation?->common_name,
            'scientific_name' => $species->scientific_name,
            'summary' => $translation?->short_name ?: $this->excerpt($translation?->summary),
            'activity_type' => $species->activity_type->value,
            'domain_type' => $species->domain_type->value,
            'habitats' => $species->habitatLinks
                ->take(3)
                ->map(fn (SpeciesHabitat $link): array => [
                    'code' => $link->habitat?->code,
                    'name' => $link->habitat?->localizedName($translation instanceof SpeciesTranslation
                        ? $translation->locale
                        : $locale),
                ])
                ->filter(static fn (array $row): bool => is_string($row['code']))
                ->values()
                ->all(),
            'verified' => $species->verification_status === SpeciesVerificationStatus::Verified,
            'media' => $primary,
            'legal_information' => $this->legalBoundary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Species $species, string $requestedLocale): array
    {
        $translation = $this->resolver->resolve($species, $requestedLocale, true);
        $locale = $translation instanceof SpeciesTranslation ? $translation->locale : $requestedLocale;
        $fallback = $this->resolver->usedFallback($species, $requestedLocale, true);

        return [
            'id' => $species->public_id,
            'slug' => $species->canonical_slug,
            'locale' => $locale,
            'requested_locale' => $requestedLocale,
            'translation_fallback' => $fallback,
            'common_name' => $translation?->common_name,
            'short_name' => $translation?->short_name,
            'scientific_name' => $species->scientific_name,
            'scientific_name_authorship' => $species->scientific_name_authorship,
            'summary' => $translation?->summary,
            'activity_type' => $species->activity_type->value,
            'domain_type' => $species->domain_type->value,
            'native_status' => $species->native_status?->value,
            'taxonomy' => [
                'rank' => $species->taxonomic_rank->value,
                'kingdom' => $this->taxon($species->kingdom),
                'phylum' => $this->taxon($species->phylum),
                'class' => $this->taxon($species->class_name),
                'order' => $this->taxon($species->order_name),
                'family' => $this->taxon($species->family),
                'genus' => $this->taxon($species->genus),
                'species_epithet' => $species->species_epithet,
            ],
            'aliases' => $species->aliases
                ->filter(static fn (SpeciesAlias $alias): bool => $alias->is_public && ($alias->locale === null || $alias->locale === $locale))
                ->map(static fn (SpeciesAlias $alias): array => [
                    'name' => $alias->name,
                    'type' => $alias->type->value,
                    'locale' => $alias->locale,
                ])
                ->values()
                ->all(),
            'identification' => [
                'overview' => $translation?->identification,
                'appearance' => $translation?->appearance,
                'traits' => $species->identificationTraits
                    ->where('locale', $locale)
                    ->sortBy('sort_order')
                    ->values()
                    ->map(static fn (SpeciesIdentificationTrait $trait): array => [
                        'category' => $trait->category->value,
                        'label' => $trait->label,
                        'description' => $trait->description,
                    ])
                    ->all(),
                'similar_species' => $this->similar($species, $locale),
            ],
            'habitats' => $species->habitatLinks->map(fn (SpeciesHabitat $link): array => [
                'code' => $link->habitat?->code,
                'name' => $link->habitat?->localizedName($locale),
                'importance' => $link->importance->value,
            ])->values()->all(),
            'behavior' => [
                'overview' => $translation?->behavior,
                'diet' => $translation?->diet,
                'breeding_notes' => $translation?->breeding_notes,
                'seasonal_behavior' => $translation?->seasonal_behavior,
                'field_notes' => $translation?->field_notes,
                'safety_notes' => $translation?->safety_notes,
                'habitat_description' => $translation?->habitat_description,
            ],
            'characteristics' => $this->characteristics($species, $locale),
            'conservation' => $species->conservationAssessments->map(fn (SpeciesConservationAssessment $row): array => [
                'assessment_system' => $row->assessment_system,
                'status_code' => $row->status_code,
                'assessment_scope' => $row->assessment_scope->value,
                'assessed_at' => $row->assessed_at?->toDateString(),
                'notes' => $row->notes,
                'source' => $row->source ? $this->publicSource($row->source) : null,
                'not_legal_status' => true,
            ])->values()->all(),
            'media' => $this->media($species, $locale),
            'sources' => $this->sources($species),
            'legal_information' => $this->legalBoundary(),
            'seo' => [
                'title' => $translation?->seo_title ?: $translation?->common_name,
                'description' => $translation?->seo_description ?: $this->excerpt($translation?->summary),
            ],
            'meta' => [
                'verified' => $species->verification_status === SpeciesVerificationStatus::Verified,
                'verification_status' => $species->verification_status->value,
                'content_version' => $species->content_version,
                'published_at' => $species->published_at?->toIso8601String(),
                'updated_at' => $species->updated_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function admin(Species $species): array
    {
        $issues = app(SpeciesPublishingValidator::class)->issues($species);

        return [
            'id' => $species->public_id,
            'canonical_slug' => $species->canonical_slug,
            'scientific_name' => $species->scientific_name,
            'scientific_name_authorship' => $species->scientific_name_authorship,
            'taxonomic_rank' => $species->taxonomic_rank->value,
            'kingdom' => $species->kingdom,
            'phylum' => $species->phylum,
            'class_name' => $species->class_name,
            'order_name' => $species->order_name,
            'family' => $species->family,
            'genus' => $species->genus,
            'species_epithet' => $species->species_epithet,
            'domain_type' => $species->domain_type->value,
            'activity_type' => $species->activity_type->value,
            'native_status' => $species->native_status?->value,
            'verification_status' => $species->verification_status->value,
            'publication_status' => $species->publication_status->value,
            'content_version' => $species->content_version,
            'no_media_required' => $species->no_media_required,
            'published_at' => $species->published_at?->toIso8601String(),
            'reviewed_at' => $species->reviewed_at?->toIso8601String(),
            'reviewed_by' => $species->reviewed_by,
            'archived_at' => $species->archived_at?->toIso8601String(),
            'created_at' => $species->created_at?->toIso8601String(),
            'updated_at' => $species->updated_at?->toIso8601String(),
            'translations' => $species->translations->map(static fn (SpeciesTranslation $row): array => $row->only([
                'locale', 'common_name', 'short_name', 'summary', 'identification', 'appearance',
                'behavior', 'diet', 'habitat_description', 'breeding_notes', 'seasonal_behavior',
                'field_notes', 'safety_notes', 'seo_title', 'seo_description',
            ]) + ['content_status' => $row->content_status->value])->values()->all(),
            'aliases' => $species->aliases->map(static fn (SpeciesAlias $row): array => [
                'id' => $row->id,
                'locale' => $row->locale,
                'name' => $row->name,
                'type' => $row->type->value,
                'is_searchable' => $row->is_searchable,
                'is_public' => $row->is_public,
                'source_id' => $row->source_id,
            ])->values()->all(),
            'characteristics' => $species->characteristic?->only([
                'average_length_min', 'average_length_max', 'length_unit',
                'average_weight_min', 'average_weight_max', 'weight_unit',
                'lifespan_years_min', 'lifespan_years_max', 'activity_pattern',
                'social_behavior', 'migration_pattern', 'water_type',
                'preferred_depth_min', 'preferred_depth_max', 'depth_unit',
                'temperature_range_min', 'temperature_range_max', 'temperature_unit',
            ]),
            'habitats' => $species->habitatLinks->map(static fn (SpeciesHabitat $row): array => [
                'code' => $row->habitat?->code,
                'importance' => $row->importance->value,
                'notes' => $row->notes,
                'source_id' => $row->source_id,
            ])->values()->all(),
            'identification_traits' => $species->identificationTraits->map(static fn (SpeciesIdentificationTrait $row): array => [
                'id' => $row->id,
                'locale' => $row->locale,
                'category' => $row->category->value,
                'label' => $row->label,
                'description' => $row->description,
                'sort_order' => $row->sort_order,
                'source_id' => $row->source_id,
            ])->values()->all(),
            'similar_species' => $species->similarFrom->map(static fn (SpeciesSimilar $row): array => [
                'id' => $row->id,
                'similar_species_id' => $row->similarSpecies?->public_id,
                'slug' => $row->similarSpecies?->canonical_slug,
                'scientific_name' => $row->similarSpecies?->scientific_name,
                'relationship_type' => $row->relationship_type->value,
                'confidence' => $row->confidence->value,
                'notes' => $row->notes,
            ])->values()->all(),
            'conservation' => $species->conservationAssessments->map(static fn (SpeciesConservationAssessment $row): array => [
                'id' => $row->id,
                'assessment_system' => $row->assessment_system,
                'status_code' => $row->status_code,
                'assessment_scope' => $row->assessment_scope->value,
                'assessed_at' => $row->assessed_at?->toDateString(),
                'source_id' => $row->source_id,
                'notes' => $row->notes,
            ])->values()->all(),
            'citations' => $species->citations->map(static fn (KnowledgeCitation $row): array => [
                'source_id' => $row->source_id,
                'source_public_id' => $row->source?->public_id,
                'claim_key' => $row->claim_key,
                'page_reference' => $row->page_reference,
                'section_reference' => $row->section_reference,
            ])->values()->all(),
            'media' => $this->media($species, SpeciesLocales::default(), admin: true),
            'publishing' => [
                'can_publish' => $issues === [],
                'issues' => $issues,
            ],
            'preview_path' => '/species/'.$species->canonical_slug,
        ];
    }

    /**
     * @return array{available: bool, message_key: string}
     */
    public function legalBoundary(): array
    {
        return [
            'available' => (bool) config('species.legal_information.available', false),
            'message_key' => (string) config(
                'species.legal_information.message_key',
                'species.legal_information_not_yet_available',
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function taxon(?string $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }

        return ['code' => $code, 'scientific_name' => $code];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function similar(Species $species, string $locale): array
    {
        $outgoing = $species->similarFrom;
        $incoming = $species->similarTo;

        $rows = $outgoing->concat($incoming);
        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            $other = $row->species_id === $species->id ? $row->similarSpecies : $row->species;
            if ($other === null || ! $other->isPublished()) {
                continue;
            }
            if (isset($seen[$other->id])) {
                continue;
            }
            $seen[$other->id] = true;
            $name = $this->resolver->resolve($other, $locale, true)?->common_name;

            $out[] = [
                'id' => $other->public_id,
                'slug' => $other->canonical_slug,
                'common_name' => $name,
                'scientific_name' => $other->scientific_name,
                'relationship_type' => $row->relationship_type->value,
                'confidence' => $row->confidence->value,
                'notes' => $row->confidence === SpeciesVerificationStatus::Verified ? $row->notes : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function characteristics(Species $species, string $locale): ?array
    {
        $row = $species->characteristic;
        if ($row === null) {
            return null;
        }

        return [
            'length' => $this->range($row->average_length_min, $row->average_length_max, $row->length_unit?->value, $locale),
            'weight' => $this->range($row->average_weight_min, $row->average_weight_max, $row->weight_unit?->value, $locale),
            'lifespan_years' => $this->range($row->lifespan_years_min, $row->lifespan_years_max, 'years', $locale),
            'activity_pattern' => $row->activity_pattern?->value,
            'social_behavior' => $row->social_behavior?->value,
            'migration_pattern' => $row->migration_pattern?->value,
            'water_type' => $row->water_type?->value,
            'depth' => $this->range($row->preferred_depth_min, $row->preferred_depth_max, $row->depth_unit?->value, $locale),
            'temperature' => $this->range($row->temperature_range_min, $row->temperature_range_max, $row->temperature_unit?->value, $locale),
        ];
    }

    /**
     * @return array{min: string|null, max: string|null, unit: string|null, formatted: string|null}|null
     */
    private function range(mixed $min, mixed $max, ?string $unit, string $locale): ?array
    {
        if ($min === null && $max === null) {
            return null;
        }

        $formatted = $this->formatRange($min, $max, $unit, $locale);

        return [
            'min' => $min !== null ? (string) $min : null,
            'max' => $max !== null ? (string) $max : null,
            'unit' => $unit,
            'formatted' => $formatted,
        ];
    }

    private function formatRange(mixed $min, mixed $max, ?string $unit, string $locale): ?string
    {
        $join = $locale === 'ka' ? '–' : '–';
        if ($min !== null && $max !== null) {
            return trim($min.$join.$max.' '.($unit ?? ''));
        }

        $value = $min ?? $max;

        return $value !== null ? trim((string) $value.' '.($unit ?? '')) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function media(Species $species, string $locale, bool $admin = false): array
    {
        $attributions = SpeciesMediaAttribution::query()
            ->whereIn('media_attachment_id', $species->mediaAttachments->pluck('id'))
            ->get()
            ->keyBy('media_attachment_id');

        return $species->mediaAttachments
            ->filter(static fn (MediaAttachment $attachment): bool => $admin || $attachment->asset?->status === MediaStatus::Ready)
            ->sortBy('sort_order')
            ->values()
            ->map(function (MediaAttachment $attachment) use ($locale, $attributions, $admin): array {
                $asset = $attachment->asset;
                $attribution = $attributions->get($attachment->id);
                $derivatives = [];
                $derivativeRows = $asset !== null ? $asset->derivatives : [];
                foreach ($derivativeRows as $derivative) {
                    $url = SpeciesMediaUrl::forDerivative($derivative);
                    if ($url === null) {
                        continue;
                    }
                    $derivatives[] = [
                        'preset' => $derivative->preset->value,
                        'format' => $derivative->format->value,
                        'url' => $url,
                        'width' => $derivative->width,
                        'height' => $derivative->height,
                    ];
                }

                return [
                    'role' => $attachment->role->value,
                    'is_primary' => $attachment->is_primary,
                    'alt_text' => $attachment->altText($locale),
                    'caption' => $attachment->caption($locale),
                    'photographer_or_creator' => $attribution?->photographer_or_creator,
                    'license' => $attribution?->license,
                    'source_url' => $attribution?->source_url,
                    'status' => $admin ? $asset?->status->value : null,
                    'derivatives' => $derivatives,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function primaryMedia(Species $species, string $locale): ?array
    {
        $items = $this->media($species, $locale);
        foreach ($items as $item) {
            if ($item['is_primary']) {
                return $item;
            }
        }

        return $items[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sources(Species $species): array
    {
        $sources = collect();
        foreach ($species->citations as $citation) {
            if ($citation->source !== null) {
                $sources->push([
                    ...$this->publicSource($citation->source),
                    'page_reference' => $citation->page_reference,
                    'section_reference' => $citation->section_reference,
                    'quotation_excerpt' => $citation->quotation_excerpt,
                ]);
            }
        }
        foreach ($species->conservationAssessments as $row) {
            if ($row->source !== null) {
                $sources->push($this->publicSource($row->source));
            }
        }

        return $sources->unique('id')->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function publicSource(KnowledgeSource $source): array
    {
        return [
            'id' => $source->public_id,
            'title' => $source->title,
            'publisher' => $source->publisher,
            'source_type' => $source->source_type->value,
            'url' => $source->url,
            'language' => $source->language,
            'published_at' => $source->published_at?->toDateString(),
            'retrieved_at' => $source->retrieved_at?->toDateString(),
            'is_official' => $source->is_official,
            'verification_status' => $source->verification_status->value,
        ];
    }

    private function excerpt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $plain = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plain === '') {
            return null;
        }

        return mb_strlen($plain) > 180 ? mb_substr($plain, 0, 177).'…' : $plain;
    }
}
