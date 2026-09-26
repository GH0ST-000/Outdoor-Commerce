<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\DTOs\PointClassificationData;
use App\Domains\Geography\Enums\SpatialAssignmentStatus;
use App\Domains\Geography\Enums\SpatialGeometryVersionStatus;
use App\Domains\Geography\Enums\SpatialImportStatus;
use App\Domains\Geography\Enums\SpatialReviewStatus;
use App\Domains\Geography\Enums\SpatialVerificationStatus;
use App\Domains\Geography\Enums\SpatialZoneStatus;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialImport;
use App\Domains\Geography\Models\SpatialSource;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Geography\Models\SpatialZoneTranslation;
use Illuminate\Support\Collection;

final class SpatialPresenter
{
    public function __construct(private readonly ZoneDisplayState $display) {}

    /**
     * @return array<string, mixed>
     */
    public function sourceAdmin(SpatialSource $source): array
    {
        return [
            'id' => $source->public_id,
            'name' => $source->name,
            'slug' => $source->slug,
            'source_type' => $source->source_type->value,
            'publisher_name' => $source->publisher_name,
            'jurisdiction_code' => $source->jurisdiction_code,
            'official_url' => $source->official_url,
            'license_name' => $source->license_name,
            'attribution_text' => $source->attribution_text,
            'verification_status' => $source->verification_status->value,
            'verified_at' => $source->verified_at?->toIso8601String(),
            'is_active' => $source->is_active,
            'is_fictional' => $source->is_fictional,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datasetAdmin(SpatialDataset $dataset): array
    {
        $dataset->loadMissing('source', 'currentVersion');

        return [
            'id' => $dataset->public_id,
            'name' => $dataset->name,
            'slug' => $dataset->slug,
            'dataset_type' => $dataset->dataset_type->value,
            'jurisdiction_code' => $dataset->jurisdiction_code,
            'status' => $dataset->status->value,
            'canonical_srid' => $dataset->canonical_srid,
            'is_fictional' => $dataset->is_fictional,
            'source' => $dataset->source !== null ? $this->sourceAdmin($dataset->source) : null,
            'current_version_id' => $dataset->currentVersion?->public_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function versionAdmin(SpatialDatasetVersion $version): array
    {
        return [
            'id' => $version->public_id,
            'version_label' => $version->version_label,
            'import_status' => $version->import_status->value,
            'review_status' => $version->review_status->value,
            'content_checksum' => $version->content_checksum,
            'original_filename' => $version->original_filename,
            'file_size' => $version->file_size,
            'feature_count' => $version->feature_count,
            'source_crs' => $version->source_crs,
            'effective_from' => $version->effective_from->toIso8601String(),
            'effective_until' => $version->effective_until?->toIso8601String(),
            'validation_summary' => $version->validation_summary,
            'property_mapping' => $version->property_mapping,
            'published_at' => $version->published_at?->toIso8601String(),
            'srid' => 4326,
            'coordinate_order' => 'longitude,latitude',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function zoneAdmin(SpatialZone $zone): array
    {
        $zone->loadMissing('translations', 'dataset');

        return [
            'id' => $zone->public_id,
            'external_identifier' => $zone->external_identifier,
            'slug' => $zone->slug,
            'zone_type' => $zone->zone_type->value,
            'default_name' => $zone->default_name,
            'status' => $zone->status->value,
            'jurisdiction_code' => $zone->jurisdiction_code,
            'region_code' => $zone->region_code,
            'is_fictional' => $zone->is_fictional,
            'translations' => $zone->translations->map(fn (SpatialZoneTranslation $row): array => [
                'locale' => $row->locale,
                'name' => $row->name,
                'short_description' => $row->short_description,
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, LegalRuleSpatialZone>  $assignments
     * @return array<string, mixed>
     */
    public function zonePublic(
        SpatialZone $zone,
        ?SpatialZoneGeometryVersion $geometry,
        Collection $assignments,
        string $locale,
        bool $includeGeometry,
    ): array {
        $source = $zone->dataset?->source;

        $geometry?->loadMissing('datasetVersion');

        return [
            'id' => $zone->public_id,
            'slug' => $zone->slug,
            'name' => $zone->localizedName($locale),
            'official_name' => $zone->default_name,
            'short_description' => $zone->localizedDescription($locale),
            'zone_type' => $zone->zone_type->value,
            'legal_state' => $this->display->summarize($assignments, null),
            'jurisdiction_code' => $zone->jurisdiction_code,
            'region_code' => $zone->region_code,
            'is_fictional' => $zone->is_fictional,
            'dataset_name' => $zone->dataset?->name,
            'dataset_version_id' => $geometry?->datasetVersion?->public_id,
            'geometry_version_id' => $geometry?->public_id,
            'effective_from' => $geometry?->effective_from?->toIso8601String(),
            'effective_until' => $geometry?->effective_until?->toIso8601String(),
            'last_verified_at' => $source?->verified_at?->toIso8601String(),
            'attribution' => $this->attribution($source),
            'srid' => 4326,
            'coordinate_order' => 'longitude,latitude',
            'precision_note' => 'Boundaries are not survey-grade unless the official source states otherwise.',
            'disclaimer' => (string) config('spatial.disclaimer_key'),
            'assignments' => $assignments->map(fn (LegalRuleSpatialZone $row): array => $this->assignmentPublic($row))->values()->all(),
            'geometry' => $includeGeometry && $geometry !== null ? $geometry->geometry()->toGeoJson() : null,
            'bounding_box' => $geometry?->boundingBox()->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function zoneMatch(SpatialZoneGeometryVersion $geometry, PointClassificationData $classification, string $locale, bool $includeGeometry): array
    {
        $zone = $geometry->zone;

        return [
            'zone' => [
                'id' => $zone->public_id,
                'name' => $zone->localizedName($locale),
                'official_name' => $zone->default_name,
                'zone_type' => $zone->zone_type->value,
                'is_fictional' => $zone->is_fictional,
            ],
            'classification' => $classification->toArray(),
            'attribution' => $this->attribution($zone->dataset?->source),
            'last_verified_at' => $zone->dataset?->source?->verified_at?->toIso8601String(),
            'geometry' => $includeGeometry ? $geometry->geometry()->toGeoJson() : null,
            'srid' => 4326,
            'coordinate_order' => 'longitude,latitude',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewportFeature(SpatialZoneGeometryVersion $geometry, string $locale, bool $includeGeometry, string $legalState = 'unknown'): array
    {
        $zone = $geometry->zone;

        return [
            'type' => 'Feature',
            'id' => $zone->public_id,
            'properties' => [
                'public_id' => $zone->public_id,
                'id' => $zone->public_id,
                'slug' => $zone->slug,
                'name' => $zone->localizedName($locale),
                'official_name' => $zone->default_name,
                'zone_type' => $zone->zone_type->value,
                'legal_state' => $legalState,
                'region_code' => $zone->region_code,
                'is_fictional' => $zone->is_fictional,
                'attribution' => $this->attribution($zone->dataset?->source),
                'last_verified_at' => $zone->dataset?->source?->verified_at?->toIso8601String(),
                'effective_from' => $geometry->effective_from->toIso8601String(),
                'effective_until' => $geometry->effective_until?->toIso8601String(),
                'srid' => 4326,
                'coordinate_order' => 'longitude,latitude',
            ],
            'geometry' => $includeGeometry ? $geometry->geometry()->toGeoJson() : null,
            'bbox' => [
                $geometry->min_longitude,
                $geometry->min_latitude,
                $geometry->max_longitude,
                $geometry->max_latitude,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentPublic(LegalRuleSpatialZone $assignment): array
    {
        $rule = $assignment->rule;

        return [
            'id' => $assignment->public_id,
            'assignment_type' => $assignment->assignment_type->value,
            'precedence' => $assignment->precedence,
            'rule' => $rule === null ? null : [
                'id' => $rule->public_id,
                'title' => $rule->title,
                'effect' => $rule->effect->value,
                'activity_type' => $rule->activity_type->value,
                'interpretation_summary' => $rule->interpretation_summary,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function importAdmin(SpatialImport $import): array
    {
        return [
            'id' => $import->public_id,
            'status' => $import->status->value,
            'features_discovered' => $import->features_discovered,
            'features_imported' => $import->features_imported,
            'features_rejected' => $import->features_rejected,
            'warnings_count' => $import->warnings_count,
            'errors_count' => $import->errors_count,
            'error_summary' => $import->error_summary,
            'started_at' => $import->started_at?->toIso8601String(),
            'completed_at' => $import->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function dashboard(): array
    {
        return [
            'sources_awaiting_verification' => SpatialSource::query()->where('verification_status', SpatialVerificationStatus::Unverified)->count(),
            'versions_awaiting_review' => SpatialDatasetVersion::query()->where('review_status', SpatialReviewStatus::InReview)->count(),
            'imports_failed' => SpatialImport::query()->whereIn('status', [SpatialImportStatus::Failed, SpatialImportStatus::PartiallyFailed])->count(),
            'geometry_awaiting_review' => SpatialZoneGeometryVersion::query()->where('status', SpatialGeometryVersionStatus::InReview)->count(),
            'zones_without_assignments' => SpatialZone::query()
                ->where('status', SpatialZoneStatus::Active)
                ->whereDoesntHave('assignments', fn ($query) => $query->where('status', SpatialAssignmentStatus::Published))
                ->count(),
            'published_versions' => SpatialDatasetVersion::query()->where('review_status', SpatialReviewStatus::Published)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function coverage(): array
    {
        return [
            'active_zones' => SpatialZone::query()->where('status', SpatialZoneStatus::Active)->count(),
            'published_geometries' => SpatialZoneGeometryVersion::query()->where('status', SpatialGeometryVersionStatus::Published)->count(),
            'published_assignments' => LegalRuleSpatialZone::query()->where('status', SpatialAssignmentStatus::Published)->count(),
            'fictional_zones' => SpatialZone::query()->where('is_fictional', true)->count(),
            'missing_assignments' => SpatialZone::query()
                ->where('status', SpatialZoneStatus::Active)
                ->whereDoesntHave('assignments', fn ($query) => $query->where('status', SpatialAssignmentStatus::Published))
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attribution(?SpatialSource $source): array
    {
        return [
            'source_name' => $source?->name,
            'publisher_name' => $source?->publisher_name,
            'license_name' => $source?->license_name,
            'attribution_text' => $source?->attribution_text,
            'official_url' => $source?->official_url,
            'verified_at' => $source?->verified_at?->toIso8601String(),
        ];
    }
}
