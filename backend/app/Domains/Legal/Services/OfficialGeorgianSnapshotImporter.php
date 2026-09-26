<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Geography\Enums\SpatialDatasetStatus;
use App\Domains\Geography\Enums\SpatialDatasetType;
use App\Domains\Geography\Enums\SpatialSourceType;
use App\Domains\Geography\Enums\SpatialVerificationStatus;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialSource;
use App\Domains\Hunting\Enums\KnowledgeSourceType;
use App\Domains\Hunting\Enums\SpeciesActivityType;
use App\Domains\Hunting\Enums\SpeciesAliasType;
use App\Domains\Hunting\Enums\SpeciesContentStatus;
use App\Domains\Hunting\Enums\SpeciesDomainType;
use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Enums\TaxonomicRank;
use App\Domains\Hunting\Models\KnowledgeSource;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesAlias;
use App\Domains\Hunting\Models\SpeciesTranslation;
use App\Domains\Legal\Enums\AuthorityType;
use App\Domains\Legal\Enums\ChangeDetectionStatus;
use App\Domains\Legal\Enums\CitationPurpose;
use App\Domains\Legal\Enums\ConditionOperator;
use App\Domains\Legal\Enums\ConditionValueType;
use App\Domains\Legal\Enums\ExtractionStatus;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalConflictSeverity;
use App\Domains\Legal\Enums\LegalConflictStatus;
use App\Domains\Legal\Enums\LegalConflictType;
use App\Domains\Legal\Enums\LegalDocumentStatus;
use App\Domains\Legal\Enums\LegalDocumentType;
use App\Domains\Legal\Enums\LegalLimitType;
use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalRuleType;
use App\Domains\Legal\Enums\LegalSourceType;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use App\Domains\Legal\Enums\LimitAppliesPer;
use App\Domains\Legal\Enums\LimitPeriod;
use App\Domains\Legal\Enums\ProvisionType;
use App\Domains\Legal\Enums\SeasonBoundaryPrecision;
use App\Domains\Legal\Enums\SeasonScheduleType;
use App\Domains\Legal\Enums\SeasonType;
use App\Domains\Legal\Exceptions\OfficialSnapshotException;
use App\Domains\Legal\Models\LegalAuthority;
use App\Domains\Legal\Models\LegalChangeDetection;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalDocument;
use App\Domains\Legal\Models\LegalDocumentVersion;
use App\Domains\Legal\Models\LegalProvision;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleCitation;
use App\Domains\Legal\Models\LegalRuleCondition;
use App\Domains\Legal\Models\LegalRuleLimit;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OfficialGeorgianSnapshotImporter
{
    public const MARKER = 'official-georgia:2026-09-26.1';

    /**
     * @return array<string, int>
     */
    public function import(?string $root = null, string $section = 'all'): array
    {
        $rootPath = $this->root($root);
        $this->verifyChecksums($rootPath);
        $counts = $this->blank();

        DB::transaction(function () use ($rootPath, $section, &$counts): void {
            $sections = $section === 'all'
                ? ['sources', 'species', 'seasons', 'limits', 'restrictions', 'fishing', 'spatial', 'assignments']
                : [$section];

            foreach ($sections as $name) {
                match ($name) {
                    'sources' => $this->importSources($rootPath, $counts),
                    'species' => $this->importSpecies($rootPath, $counts),
                    'seasons' => $this->importSeasons($rootPath, $counts),
                    'limits' => $this->importLimits($rootPath, $counts),
                    'restrictions' => $this->importRestrictions($rootPath, $counts),
                    'fishing' => $this->importFishing($rootPath, $counts),
                    'spatial' => $this->importSpatialCatalogue($rootPath, $counts),
                    'assignments' => $this->importAssignments($rootPath, $counts),
                    default => throw new OfficialSnapshotException('Unknown import section: '.$name),
                };
            }
        });

        return $counts;
    }

    public function verifyChecksums(?string $root = null): void
    {
        $rootPath = $this->root($root);
        $manifest = $this->read($rootPath, 'manifest.json');
        $files = $manifest['files'] ?? null;
        if (! is_array($files) || $files === []) {
            throw new OfficialSnapshotException('The snapshot manifest has no files.');
        }

        foreach ($files as $file) {
            if (! is_array($file)) {
                throw new OfficialSnapshotException('A manifest file entry is malformed.');
            }
            $relative = (string) ($file['path'] ?? '');
            $expected = (string) ($file['sha256'] ?? '');
            if ($relative === '' || str_contains($relative, '..') || strlen($expected) !== 64) {
                throw new OfficialSnapshotException('A manifest file entry is malformed.');
            }
            $path = $rootPath.'/'.$relative;
            if (! is_file($path)) {
                throw new OfficialSnapshotException('Snapshot file is missing: '.$relative);
            }
            $actual = hash_file('sha256', $path);
            if (! is_string($actual) || ! hash_equals($expected, $actual)) {
                throw new OfficialSnapshotException('Snapshot checksum mismatch: '.$relative);
            }
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importSources(string $root, array &$counts): void
    {
        $payload = $this->read($root, 'sources/documents.json');
        $authorities = [];
        foreach ($this->list($payload, 'authorities') as $row) {
            $authorities[(string) $row['slug']] = $this->upsertAuthority($row, $counts);
        }
        $sources = [];
        foreach ($this->list($payload, 'sources') as $row) {
            $authority = $authorities[(string) $row['authority_slug']] ?? null;
            if (! $authority instanceof LegalAuthority) {
                throw new OfficialSnapshotException('Legal source authority is missing.');
            }
            $sources[(string) $row['slug']] = $this->upsertSource($row, $authority, $counts);
        }

        foreach ($this->list($payload, 'documents') as $row) {
            $source = $sources[(string) $row['source_slug']] ?? null;
            $authority = $authorities[(string) $row['authority_slug']] ?? null;
            if (! $source instanceof LegalSource || ! $authority instanceof LegalAuthority) {
                throw new OfficialSnapshotException('Legal document source is missing: '.(string) ($row['slug'] ?? ''));
            }
            $this->upsertDocument($row, $source, $authority, $counts);
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importSpecies(string $root, array &$counts): void
    {
        $this->importSources($root, $counts);
        $payload = $this->read($root, 'hunting/migratory_bird_rules.json');
        $document = $this->document('ge-order-95');
        $knowledge = $this->knowledgeSource($document);

        foreach ($this->list($payload, 'records') as $row) {
            if (($row['import'] ?? false) !== true) {
                $counts['skipped']++;

                continue;
            }
            $scientific = $this->scientific($row);
            $species = Species::withTrashed()->where('scientific_name_normalized', $this->normalizeScientificName($scientific))->first();
            if ($species instanceof Species && ($species->trashed() || $species->isPublished() || $species->reviewed_at !== null)) {
                $counts['skipped']++;

                continue;
            }

            $created = false;
            if (! $species instanceof Species) {
                $species = Species::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'canonical_slug' => (string) $row['canonical_slug'],
                    'scientific_name' => $scientific,
                    'scientific_name_normalized' => $this->normalizeScientificName($scientific),
                    'taxonomic_rank' => TaxonomicRank::Species,
                    'kingdom' => 'Animalia',
                    'genus' => (string) $row['genus'],
                    'species_epithet' => (string) $row['species_epithet'],
                    'domain_type' => SpeciesDomainType::Migratory,
                    'activity_type' => SpeciesActivityType::Hunting,
                    'verification_status' => SpeciesVerificationStatus::NeedsReview,
                    'publication_status' => SpeciesPublicationStatus::InReview,
                    'no_media_required' => true,
                ]);
                $created = true;
                $counts['created']++;
                $counts['species']++;
            }

            $name = (string) $row['georgian_name'];
            $summary = 'ქართული სახელი „'.$name.'“ და სამეცნიერო სახელი '.$scientific.' აღებულია მინისტრის ბრძანება №95-ის დანართი 1-იდან (კონსოლიდირებული პუბლიკაცია 07/08/2024). ეს ჩანაწერი ბიოლოგიურ აღწერას არ შეიცავს.';
            $translation = $species->translations()->where('locale', 'ka')->first();
            if (! $translation instanceof SpeciesTranslation) {
                SpeciesTranslation::query()->create([
                    'species_id' => $species->id,
                    'locale' => 'ka',
                    'common_name' => $name,
                    'summary' => $summary,
                    'identification' => 'სამეცნიერო სახელი წყაროში: '.$scientific.'.',
                    'content_status' => SpeciesContentStatus::Draft,
                ]);
                if (! $created) {
                    $counts['updated']++;
                }
            } elseif ($this->ownsSpeciesText($translation) && $translation->common_name !== $name) {
                $translation->fill([
                    'common_name' => $name,
                    'summary' => $summary,
                    'identification' => 'სამეცნიერო სახელი წყაროში: '.$scientific.'.',
                ])->save();
                $counts['updated']++;
            } else {
                $counts['skipped']++;
            }

            foreach ($row['aliases_ka'] ?? [] as $alias) {
                if (! is_string($alias) || $alias === '' || $alias === $name) {
                    continue;
                }
                $normalized = mb_strtolower($alias, 'UTF-8');
                $exists = SpeciesAlias::query()
                    ->where('species_id', $species->id)
                    ->where('locale', 'ka')
                    ->where('normalized_name', $normalized)
                    ->exists();
                if ($exists) {
                    $counts['skipped']++;

                    continue;
                }
                SpeciesAlias::query()->create([
                    'species_id' => $species->id,
                    'locale' => 'ka',
                    'name' => $alias,
                    'normalized_name' => $normalized,
                    'type' => SpeciesAliasType::CommonAlias,
                    'is_searchable' => true,
                    'is_public' => false,
                    'source_id' => $knowledge->id,
                ]);
                $counts['created']++;
            }
        }

        foreach ($this->list($this->read($root, 'hunting/hunting_objects.json'), 'records') as $row) {
            if (($row['import_as_current'] ?? true) === true) {
                throw new OfficialSnapshotException('Historical hunting-object rows must not be imported as current.');
            }
            $counts['skipped']++;
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importSeasons(string $root, array &$counts): void
    {
        $this->importSpecies($root, $counts);
        $periods = $this->read($root, 'hunting/hunting_periods.json');
        $annex = $this->provision('order-95:annex-1');
        $openingProvision = $this->provision('order-95:article-3:paragraph-2');

        foreach ($this->list($periods, 'records') as $row) {
            $species = $this->species($this->scientific($row));
            $rule = $this->upsertRule($row, $species, '2024-08-07', $counts);
            $this->cite($rule, $annex, CitationPurpose::Limit, (string) $row['official_period_text']);
            $this->conditionsForScope($rule, $row);
            if (($row['creates_season_definition'] ?? false) === true) {
                $this->upsertSeason($rule, $species, $row, $counts);
            }
        }

        $opening = $periods['general_opening'] ?? null;
        if (! is_array($opening)) {
            throw new OfficialSnapshotException('The general opening rule is missing.');
        }
        $openingRule = $this->upsertRule($opening, null, '2024-08-07', $counts);
        $this->cite($openingRule, $openingProvision, CitationPurpose::Authority, (string) $opening['official_text']);
        if (($opening['creates_season_definition'] ?? true) === true) {
            throw new OfficialSnapshotException('The disputed recurring opening must not create a season definition.');
        }

        $annual = $this->read($root, 'hunting/annual_seasons/2026-2027.json');
        if (($annual['creates_season_definition'] ?? true) === true) {
            throw new OfficialSnapshotException('The disputed 2026–2027 window must not create a season definition.');
        }
        $annualRule = $this->upsertRule($annual, null, '2026-07-24', $counts);
        $this->cite($annualRule, $this->provision('mepa-news-26537:body'), CitationPurpose::Authority, (string) $annual['official_text']);
        $this->dateCondition($annualRule, ConditionOperator::GreaterThanOrEqual, '2026-08-22');
        $this->dateCondition($annualRule, ConditionOperator::LessThan, '2027-03-01');
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importLimits(string $root, array &$counts): void
    {
        $this->importSeasons($root, $counts);
        foreach ($this->list($this->read($root, 'hunting/daily_limits.json'), 'records') as $row) {
            $rule = LegalRule::query()->where('slug', (string) $row['rule_slug'])->first();
            if (! $rule instanceof LegalRule || ! $this->canWriteRule($rule)) {
                $counts['skipped']++;

                continue;
            }
            $limit = LegalRuleLimit::query()->where('legal_rule_id', $rule->id)->where('limit_type', LegalLimitType::Bag)->first();
            $attributes = [
                'amount' => $row['amount'],
                'unit' => (string) $row['unit'],
                'period' => LimitPeriod::Day,
                'applies_per' => LimitAppliesPer::Person,
                'notes' => (string) $row['notes'],
            ];
            if (! $limit instanceof LegalRuleLimit) {
                LegalRuleLimit::query()->create(['legal_rule_id' => $rule->id, 'limit_type' => LegalLimitType::Bag, ...$attributes]);
                $counts['created']++;
                $counts['limits']++;
            } else {
                $limit->fill($attributes)->save();
                $counts['skipped']++;
            }
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importRestrictions(string $root, array &$counts): void
    {
        $this->importLimits($root, $counts);
        foreach (['hunting/location_restrictions.json', 'hunting/method_restrictions.json', 'hunting/permit_requirements.json'] as $file) {
            foreach ($this->list($this->read($root, $file), 'records') as $row) {
                $rule = $this->upsertRule($row, null, str_starts_with((string) $row['slug'], 'ge-mepa-') ? '2026-07-24' : '2024-08-07', $counts);
                $this->cite($rule, $this->provision((string) $row['provision_reference']), CitationPurpose::Authority, (string) $row['official_text']);
                if (($row['amount'] ?? null) !== null) {
                    $this->upsertFee($rule, (string) $row['amount'], (string) $row['unit'], $counts);
                }
            }
        }

        foreach ($this->list($this->read($root, 'hunting/source_conflicts.json'), 'records') as $row) {
            $first = LegalRule::query()->where('slug', (string) $row['first_slug'])->first();
            $second = LegalRule::query()->where('slug', (string) $row['second_slug'])->first();
            if (! $first instanceof LegalRule || ! $second instanceof LegalRule) {
                throw new OfficialSnapshotException('Conflict rules are missing: '.(string) $row['key']);
            }
            $this->upsertConflict($first, $second, $row, $counts);
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importFishing(string $root, array &$counts): void
    {
        $this->importSources($root, $counts);
        foreach (['species_restrictions', 'closed_periods', 'waterbody_restrictions', 'method_restrictions', 'equipment_restrictions'] as $name) {
            $payload = $this->read($root, 'fishing/'.$name.'.json');
            if (($payload['records'] ?? null) !== []) {
                throw new OfficialSnapshotException('Fishing snapshot '.$name.' contains records, but the current regulation was not public.');
            }
            $counts['skipped']++;
        }
        if (is_file($root.'/fishing/coordinate_zones.geojson')) {
            throw new OfficialSnapshotException('Fishing coordinate geometry must not be invented.');
        }

        $document = $this->document('ge-fishing-regulation-423');
        $this->upsertGap($document->legal_source_id, $document->current_version_id, 'fishing_regulation_423_not_public', 'Current consolidated fishing regulation was not public. No recreational rule was imported.', $counts);
        $order = $this->document('ge-order-18');
        $this->upsertGap($order->legal_source_id, $order->current_version_id, 'order_18_current_list_not_public', 'Current consolidated hunting-object list was not public. The 2009 original was not imported as current.', $counts);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importSpatialCatalogue(string $root, array &$counts): void
    {
        $this->importSources($root, $counts);
        $payload = $this->read($root, 'spatial/datasets.json');
        if (($payload['geometry_retrieved'] ?? true) !== false) {
            throw new OfficialSnapshotException('Geometry must not be marked retrieved unless a GeoJSON file was stored.');
        }
        foreach ($this->list($payload, 'records') as $row) {
            if (($row['geometry_file'] ?? null) !== null) {
                throw new OfficialSnapshotException('Official geometry file is recorded without a downloaded dataset.');
            }
            $authority = LegalAuthority::query()->where('slug', (string) $row['authority_slug'])->first();
            $source = SpatialSource::query()->where('official_identifier', (string) $row['source_code'])->first();
            if (! $source instanceof SpatialSource) {
                SpatialSource::query()->create([
                    'legal_authority_id' => $authority?->id,
                    'name' => (string) $row['name_ka'],
                    'slug' => 'ge-gis-'.strtolower((string) $row['source_code']),
                    'source_type' => SpatialSourceType::OfficialGeoportal,
                    'official_url' => (string) $row['detail_url'],
                    'official_identifier' => (string) $row['source_code'],
                    'publisher_name' => (string) $row['publisher'],
                    'jurisdiction_code' => 'GE',
                    'attribution_text' => (string) $row['publisher'],
                    'allowed_usage_notes' => 'Catalogue metadata only. Geometry was not downloaded. Import stays draft until a reviewed GeoJSON file is supplied.',
                    'verification_status' => SpatialVerificationStatus::PendingReview,
                    'is_active' => true,
                    'is_fictional' => false,
                ]);
                $source = SpatialSource::query()->where('official_identifier', (string) $row['source_code'])->firstOrFail();
                $counts['created']++;
            } else {
                $counts['skipped']++;
            }

            $dataset = SpatialDataset::query()->where('spatial_source_id', $source->id)->where('slug', (string) $row['dataset_slug'])->first();
            if (! $dataset instanceof SpatialDataset) {
                SpatialDataset::query()->create([
                    'spatial_source_id' => $source->id,
                    'name' => (string) $row['name_ka'],
                    'slug' => (string) $row['dataset_slug'],
                    'dataset_type' => SpatialDatasetType::from((string) $row['dataset_type']),
                    'jurisdiction_code' => 'GE',
                    'description' => (string) $row['layer_name'].' — geometry not downloaded.',
                    'native_crs' => 'EPSG:4326',
                    'canonical_srid' => 4326,
                    'status' => SpatialDatasetStatus::Draft,
                    'is_fictional' => false,
                ]);
                $counts['created']++;
                $counts['datasets']++;
            } else {
                $counts['skipped']++;
            }
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importAssignments(string $root, array &$counts): void
    {
        $this->importSpatialCatalogue($root, $counts);
        foreach ($this->list($this->read($root, 'coverage_gaps.json'), 'records') as $row) {
            if (($row['effect'] ?? '') === 'allow') {
                throw new OfficialSnapshotException('A coverage gap must not be stored as permission.');
            }
            $counts['gaps']++;
        }
        $counts['assignments'] += 0;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $counts
     */
    private function upsertAuthority(array $row, array &$counts): LegalAuthority
    {
        $authority = LegalAuthority::query()->where('slug', (string) $row['slug'])->first();
        $attributes = [
            'name' => (string) $row['name'],
            'authority_type' => AuthorityType::from((string) $row['authority_type']),
            'country_code' => 'GE',
            'jurisdiction_code' => 'GE',
            'official_website_url' => (string) $row['official_website_url'],
            'is_active' => true,
            'is_fictional' => false,
        ];
        if (! $authority instanceof LegalAuthority) {
            $counts['created']++;

            return LegalAuthority::query()->create(['slug' => (string) $row['slug'], ...$attributes]);
        }
        if ($authority->is_fictional) {
            $counts['skipped']++;

            return $authority;
        }
        $authority->fill($attributes)->save();
        $counts['skipped']++;

        return $authority;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $counts
     */
    private function upsertSource(array $row, LegalAuthority $authority, array &$counts): LegalSource
    {
        $source = LegalSource::query()->where('slug', (string) $row['slug'])->first();
        $attributes = [
            'legal_authority_id' => $authority->id,
            'name' => (string) $row['name'],
            'source_type' => LegalSourceType::from((string) $row['source_type']),
            'official_base_url' => (string) $row['official_base_url'],
            'allowed_domain' => (string) $row['allowed_domain'],
            'language_code' => 'ka',
            'jurisdiction_code' => 'GE',
            'trust_level' => (string) $row['trust_level'],
            'notes' => (string) $row['notes'],
            'is_active' => true,
            'monitor_for_changes' => true,
        ];
        if (! $source instanceof LegalSource) {
            $counts['created']++;

            return LegalSource::query()->create([
                'slug' => (string) $row['slug'],
                'verification_status' => LegalVerificationStatus::Unverified,
                ...$attributes,
            ]);
        }
        if ($source->verification_status === LegalVerificationStatus::Verified) {
            $counts['skipped']++;

            return $source;
        }
        $source->fill($attributes)->save();
        $counts['skipped']++;

        return $source;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $counts
     */
    private function upsertDocument(array $row, LegalSource $source, LegalAuthority $authority, array &$counts): void
    {
        $document = LegalDocument::query()->where('slug', (string) $row['slug'])->first();
        $attributes = [
            'legal_source_id' => $source->id,
            'legal_authority_id' => $authority->id,
            'title' => (string) $row['title'],
            'official_identifier' => (string) $row['official_identifier'],
            'document_type' => LegalDocumentType::from((string) $row['document_type']),
            'jurisdiction_code' => 'GE',
            'language_code' => 'ka',
            'official_url' => (string) $row['official_url'],
            'publication_date' => $row['publication_date'],
            'original_effective_date' => $row['original_effective_date'],
        ];
        if (! $document instanceof LegalDocument) {
            $document = LegalDocument::query()->create([
                'slug' => (string) $row['slug'],
                'status' => LegalDocumentStatus::Draft,
                ...$attributes,
            ]);
            $counts['created']++;
        } elseif ($document->status !== LegalDocumentStatus::Draft) {
            $counts['skipped']++;
        } else {
            $document->fill($attributes)->save();
            $counts['skipped']++;
        }

        $excerpt = (string) $row['excerpt'];
        $checksum = hash('sha256', (string) $row['version_label']."\n".$excerpt);
        $version = LegalDocumentVersion::query()
            ->where('legal_document_id', $document->id)
            ->where('content_checksum', $checksum)
            ->first();
        if (! $version instanceof LegalDocumentVersion) {
            $current = $document->current_version_id
                ? LegalDocumentVersion::query()->find($document->current_version_id)
                : null;
            $version = LegalDocumentVersion::query()->create([
                'legal_document_id' => $document->id,
                'version_label' => (string) $row['version_label'],
                'source_url' => (string) $row['official_url'],
                'source_published_at' => $row['source_published_at'],
                'retrieved_at' => Carbon::parse('2026-09-25T21:10:00Z'),
                'content_checksum' => $checksum,
                'checksum_algorithm' => 'sha256',
                'mime_type' => 'text/plain',
                'file_size' => strlen($excerpt),
                'original_filename' => (string) $row['slug'].'.txt',
                'extracted_text' => $excerpt,
                'extraction_status' => ($row['retrieval_status'] ?? '') === 'current_consolidation_not_public'
                    ? ExtractionStatus::ManualOnly
                    : ExtractionStatus::Completed,
                'verification_status' => LegalVerificationStatus::PendingReview,
                'review_status' => LegalReviewStatus::InReview,
                'change_summary' => (string) $row['change_summary'],
                'supersedes_version_id' => $current?->id,
            ]);
            if ($document->status === LegalDocumentStatus::Draft) {
                $document->current_version_id = $version->id;
                $document->save();
            }
            $counts['created']++;
            $counts['versions']++;
        } else {
            $counts['skipped']++;
        }

        $order = 0;
        foreach ($row['provisions'] ?? [] as $provision) {
            if (! is_array($provision)) {
                throw new OfficialSnapshotException('A provision is malformed.');
            }
            $order++;
            $existing = LegalProvision::query()
                ->where('legal_document_version_id', $version->id)
                ->where('reference_code', (string) $provision['reference_code'])
                ->first();
            if ($existing instanceof LegalProvision) {
                $counts['skipped']++;

                continue;
            }
            LegalProvision::query()->create([
                'legal_document_version_id' => $version->id,
                'provision_type' => $this->provisionType((string) $provision['provision_type']),
                'reference_code' => (string) $provision['reference_code'],
                'heading' => (string) $provision['heading'],
                'official_text' => (string) $provision['official_text'],
                'normalized_summary' => null,
                'sort_order' => $order,
                'review_status' => LegalReviewStatus::InReview,
            ]);
            $counts['created']++;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $counts
     */
    private function upsertRule(array $row, ?Species $species, string $effectiveOn, array &$counts): LegalRule
    {
        if (($row['publish'] ?? false) === true) {
            throw new OfficialSnapshotException('Snapshot rules cannot request publication.');
        }
        $slug = (string) $row['slug'];
        $rule = LegalRule::withTrashed()->where('slug', $slug)->first();
        $attributes = [
            'title' => mb_substr((string) ($row['title_ka'] ?? $row['slug']), 0, 255),
            'activity_type' => LegalActivityType::Hunting,
            'rule_type' => LegalRuleType::from((string) $row['rule_type']),
            'effect' => LegalRuleEffect::from((string) $row['effect']),
            'species_id' => $species?->id,
            'jurisdiction_code' => 'GE',
            'effective_from' => Carbon::parse($effectiveOn, 'Asia/Tbilisi'),
            'status' => LegalRuleStatus::InReview,
            'verification_level' => LegalVerificationLevel::ProvisionVerified,
            'interpretation_summary' => 'შეუმოწმებელი ამონაწერი. '.mb_substr((string) ($row['official_text'] ?? $row['official_period_text'] ?? ''), 0, 1500),
            'internal_notes' => self::MARKER."\n".(string) ($row['notes'] ?? ''),
        ];
        if ($rule instanceof LegalRule && ($rule->trashed() || ! $this->canWriteRule($rule))) {
            $counts['skipped']++;
            if ($rule->trashed()) {
                throw new OfficialSnapshotException('Rule slug is held by a deleted record: '.$slug);
            }

            return $rule;
        }
        if (! $rule instanceof LegalRule) {
            $counts['created']++;
            $counts['rules']++;

            return LegalRule::query()->create(['slug' => $slug, ...$attributes]);
        }
        $rule->fill($attributes)->save();
        $counts['skipped']++;

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $counts
     */
    private function upsertSeason(LegalRule $rule, Species $species, array $row, array &$counts): void
    {
        if (($row['schedule_type'] ?? '') !== SeasonScheduleType::AnnualRecurring->value) {
            throw new OfficialSnapshotException('Only explicit month/day seasons can be stored. Unresolved weekday rules cannot.');
        }
        $definition = LegalSeasonDefinition::withTrashed()->where('legal_rule_id', $rule->id)->first();
        $attributes = [
            'species_id' => $species->id,
            'activity_type' => LegalActivityType::Hunting,
            'season_type' => SeasonType::Opening,
            'schedule_type' => SeasonScheduleType::AnnualRecurring,
            'jurisdiction_code' => 'GE',
            'timezone' => 'Asia/Tbilisi',
            'boundary_precision' => SeasonBoundaryPrecision::Date,
            'start_month' => $row['start_month'],
            'start_day' => $row['start_day'],
            'end_month' => $row['end_month'],
            'end_day' => $row['end_day'],
            'crosses_calendar_year' => (bool) $row['crosses_calendar_year'],
            'status' => LegalRuleStatus::InReview,
            'verification_level' => LegalVerificationLevel::ProvisionVerified,
            'internal_notes' => self::MARKER."\n".(string) $row['official_period_text'],
        ];
        if ($definition instanceof LegalSeasonDefinition && ($definition->trashed() || $definition->reviewed_at !== null || $definition->status === LegalRuleStatus::Published)) {
            $counts['skipped']++;

            return;
        }
        if (! $definition instanceof LegalSeasonDefinition) {
            LegalSeasonDefinition::query()->create([
                'public_id' => (string) Str::uuid(),
                'legal_rule_id' => $rule->id,
                ...$attributes,
            ]);
            $counts['created']++;
            $counts['seasons']++;

            return;
        }
        $definition->fill($attributes)->save();
        $counts['skipped']++;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function conditionsForScope(LegalRule $rule, array $row): void
    {
        if (! $this->canWriteRule($rule)) {
            return;
        }
        foreach (['excluded_municipalities' => ConditionOperator::NotEquals, 'included_municipalities' => ConditionOperator::Equals] as $key => $operator) {
            foreach ($row[$key] ?? [] as $name) {
                if (! is_string($name) || $name === '') {
                    continue;
                }
                LegalRuleCondition::query()->firstOrCreate([
                    'legal_rule_id' => $rule->id,
                    'condition_type' => 'region',
                    'operator' => $operator,
                    'string_value' => $name,
                ], [
                    'value_type' => ConditionValueType::String,
                    'group_key' => $key,
                ]);
            }
        }
    }

    private function dateCondition(LegalRule $rule, ConditionOperator $operator, string $date): void
    {
        if (! $this->canWriteRule($rule)) {
            return;
        }
        LegalRuleCondition::query()->firstOrCreate([
            'legal_rule_id' => $rule->id,
            'condition_type' => 'date',
            'operator' => $operator,
            'date_value' => $date,
        ], [
            'value_type' => ConditionValueType::Date,
            'group_key' => 'fixed_2026_2027',
        ]);
    }

    private function cite(LegalRule $rule, LegalProvision $provision, CitationPurpose $purpose, string $excerpt): void
    {
        LegalRuleCitation::query()->firstOrCreate([
            'legal_rule_id' => $rule->id,
            'legal_provision_id' => $provision->id,
        ], [
            'citation_purpose' => $purpose,
            'quoted_excerpt' => mb_substr(trim($excerpt), 0, 400),
            'is_primary' => true,
        ]);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function upsertFee(LegalRule $rule, string $amount, string $unit, array &$counts): void
    {
        if (! $this->canWriteRule($rule)) {
            $counts['skipped']++;

            return;
        }
        $limit = LegalRuleLimit::query()->where('legal_rule_id', $rule->id)->where('limit_type', LegalLimitType::Other)->first();
        $attributes = [
            'amount' => $amount,
            'unit' => $unit,
            'period' => LimitPeriod::NotApplicable,
            'applies_per' => LimitAppliesPer::Person,
            'notes' => self::MARKER,
        ];
        if (! $limit instanceof LegalRuleLimit) {
            LegalRuleLimit::query()->create(['legal_rule_id' => $rule->id, 'limit_type' => LegalLimitType::Other, ...$attributes]);
            $counts['created']++;

            return;
        }
        $counts['skipped']++;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $counts
     */
    private function upsertConflict(LegalRule $first, LegalRule $second, array $row, array &$counts): void
    {
        $existing = LegalConflict::query()
            ->where(function ($query) use ($first, $second): void {
                $query->where('first_rule_id', $first->id)->where('second_rule_id', $second->id);
            })
            ->orWhere(function ($query) use ($first, $second): void {
                $query->where('first_rule_id', $second->id)->where('second_rule_id', $first->id);
            })
            ->first();
        if ($existing instanceof LegalConflict) {
            $counts['skipped']++;

            return;
        }
        LegalConflict::query()->create([
            'first_rule_id' => min($first->id, $second->id),
            'second_rule_id' => max($first->id, $second->id),
            'conflict_type' => LegalConflictType::from((string) $row['conflict_type']),
            'severity' => LegalConflictSeverity::from((string) $row['severity']),
            'status' => LegalConflictStatus::Open,
            'detected_at' => now(),
            'detected_by' => 'official-snapshot',
            'evidence' => (string) $row['evidence'],
        ]);
        $counts['created']++;
        $counts['conflicts']++;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function upsertGap(int $sourceId, ?int $versionId, string $signal, string $notes, array &$counts): void
    {
        $existing = LegalChangeDetection::query()->where('legal_source_id', $sourceId)->where('signal', $signal)->first();
        if ($existing instanceof LegalChangeDetection) {
            $counts['skipped']++;

            return;
        }
        LegalChangeDetection::query()->create([
            'legal_source_id' => $sourceId,
            'legal_document_version_id' => $versionId,
            'status' => ChangeDetectionStatus::Open,
            'signal' => $signal,
            'internal_notes' => self::MARKER."\n".$notes,
            'detected_at' => Carbon::parse('2026-09-25T21:10:00Z'),
            'current_metadata' => ['effect' => 'unknown'],
        ]);
        $counts['created']++;
        $counts['gaps']++;
    }

    private function knowledgeSource(LegalDocument $document): KnowledgeSource
    {
        $version = $document->currentVersion;
        $existing = KnowledgeSource::query()->where('document_identifier', 'matsne:2166315')->first();
        if ($existing instanceof KnowledgeSource) {
            return $existing;
        }

        return KnowledgeSource::query()->create([
            'public_id' => (string) Str::uuid(),
            'title' => 'ბრძანება №95, დანართი 1',
            'publisher' => 'საქართველოს გარემოს დაცვისა და სოფლის მეურნეობის სამინისტრო',
            'source_type' => KnowledgeSourceType::Government,
            'url' => $document->official_url,
            'document_identifier' => 'matsne:2166315',
            'language' => 'ka',
            'published_at' => '2024-08-07',
            'retrieved_at' => '2026-09-25',
            'is_official' => true,
            'verification_status' => SpeciesVerificationStatus::NeedsReview,
            'checksum' => $version?->content_checksum,
            'notes' => self::MARKER,
        ]);
    }

    private function document(string $slug): LegalDocument
    {
        $document = LegalDocument::query()->where('slug', $slug)->first();
        if (! $document instanceof LegalDocument) {
            throw new OfficialSnapshotException('Document was not imported: '.$slug);
        }

        return $document;
    }

    private function provision(string $reference): LegalProvision
    {
        $provision = LegalProvision::query()->where('reference_code', $reference)->first();
        if (! $provision instanceof LegalProvision) {
            throw new OfficialSnapshotException('Provision was not imported: '.$reference);
        }

        return $provision;
    }

    private function species(string $scientific): Species
    {
        $species = Species::query()->where('scientific_name_normalized', $this->normalizeScientificName($scientific))->first();
        if (! $species instanceof Species) {
            throw new OfficialSnapshotException('Species was not imported: '.$scientific);
        }

        return $species;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function scientific(array $row): string
    {
        $scientific = trim((string) ($row['scientific_name'] ?? ''));
        if (preg_match('/^[A-Z][a-z]+ [a-z]+$/', $scientific) !== 1) {
            throw new OfficialSnapshotException('Scientific name is not a binomial from the source: '.$scientific);
        }

        return $scientific;
    }

    private function canWriteRule(LegalRule $rule): bool
    {
        return ! $rule->trashed()
            && $rule->reviewed_by === null
            && $rule->published_at === null
            && in_array($rule->status, [LegalRuleStatus::Draft, LegalRuleStatus::InReview], true)
            && str_starts_with((string) $rule->internal_notes, 'official-georgia:');
    }

    private function ownsSpeciesText(SpeciesTranslation $translation): bool
    {
        return str_starts_with($translation->summary, 'ქართული სახელი „');
    }

    private function provisionType(string $type): ProvisionType
    {
        return match ($type) {
            'annex' => ProvisionType::Appendix,
            'notice' => ProvisionType::Note,
            'paragraph' => ProvisionType::Paragraph,
            'article' => ProvisionType::Article,
            default => throw new OfficialSnapshotException('Unsupported provision type: '.$type),
        };
    }

    private function normalizeScientificName(string $value): string
    {
        $value = trim($value);
        if (class_exists(\Normalizer::class)) {
            $nfc = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($nfc) && $nfc !== '') {
                $value = $nfc;
            }
        }
        $collapsed = preg_replace('/\s+/u', ' ', $value);

        return mb_strtolower(trim(is_string($collapsed) ? $collapsed : $value), 'UTF-8');
    }

    private function root(?string $root): string
    {
        $path = $root ?: base_path('database/data/official/georgia');
        $real = realpath($path);
        if ($real === false || ! is_dir($real)) {
            throw new OfficialSnapshotException('Official snapshot directory is missing.');
        }

        return $real;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $root, string $relative): array
    {
        if (str_contains($relative, '..')) {
            throw new OfficialSnapshotException('Snapshot path is not allowed.');
        }
        $path = $root.'/'.$relative;
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new OfficialSnapshotException('Snapshot JSON is malformed: '.$relative);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function list(array $payload, string $key): array
    {
        $rows = $payload[$key] ?? null;
        if (! is_array($rows)) {
            throw new OfficialSnapshotException('Snapshot list is missing: '.$key);
        }
        $list = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new OfficialSnapshotException('Snapshot row is malformed: '.$key);
            }
            $list[] = $row;
        }

        return $list;
    }

    /**
     * @return array<string, int>
     */
    private function blank(): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'species' => 0,
            'rules' => 0,
            'seasons' => 0,
            'limits' => 0,
            'conflicts' => 0,
            'versions' => 0,
            'datasets' => 0,
            'gaps' => 0,
            'assignments' => 0,
        ];
    }
}
