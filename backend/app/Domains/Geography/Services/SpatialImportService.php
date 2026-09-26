<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\Enums\SpatialFeatureErrorStatus;
use App\Domains\Geography\Enums\SpatialGeometryVersionStatus;
use App\Domains\Geography\Enums\SpatialImportStatus;
use App\Domains\Geography\Enums\SpatialValidationStatus;
use App\Domains\Geography\Enums\SpatialZoneStatus;
use App\Domains\Geography\Enums\SpatialZoneType;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialImport;
use App\Domains\Geography\Models\SpatialImportError;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Geography\Models\SpatialZoneTranslation;
use App\Domains\Geography\Support\MultipolygonGeometry;
use App\Domains\Geography\Support\SpatialDriver;
use App\Domains\Geography\Support\SpatialLogger;
use App\Domains\Geography\Support\SpatialSlug;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class SpatialImportService
{
    public function __construct(
        private readonly SpatialFileIngestionService $files,
        private readonly GeoJsonValidator $geojson,
        private readonly GeometryValidationService $geometry,
        private readonly SpatialAuditRecorder $audit,
        private readonly SpatialLogger $logger,
    ) {}

    public function start(SpatialDatasetVersion $version, User $actor): SpatialImport
    {
        if ($version->isImmutable()) {
            throw SpatialException::versionImmutable();
        }
        if (! is_array($version->property_mapping) || ($version->property_mapping['external_identifier'] ?? '') === '') {
            throw SpatialException::mappingInvalid('An external_identifier mapping is required before import.');
        }

        $import = SpatialImport::query()->create([
            'spatial_dataset_version_id' => $version->id,
            'status' => SpatialImportStatus::Pending,
            'triggered_by' => $actor->id,
        ]);

        $this->audit->record(AuditEvent::SpatialImportStarted, $actor, 'spatial_import', $import->public_id);

        return $import;
    }

    public function run(SpatialImport $import): SpatialImport
    {
        $import->loadMissing('datasetVersion.dataset');
        $version = $import->datasetVersion ?? throw SpatialException::notFound('Spatial dataset version');
        $actorId = $import->triggered_by;

        $import->status = SpatialImportStatus::Importing;
        $import->started_at = now();
        $import->save();

        try {
            $contents = $this->files->read($version->storage_disk, $version->storage_path);
            $parsed = $this->geojson->parse($contents, (string) $version->source_crs);
            $mapping = $version->property_mapping ?? [];
            $import->features_discovered = $parsed['feature_count'];
            $import->save();

            DB::transaction(function () use ($parsed, $mapping, $version, $import, $actorId): void {
                foreach ($parsed['features'] as $feature) {
                    try {
                        $this->importFeature($version, $feature, $mapping, $actorId);
                        $import->features_imported++;
                        $import->features_validated++;
                    } catch (Throwable $exception) {
                        $import->features_rejected++;
                        $import->errors_count++;
                        SpatialImportError::query()->create([
                            'spatial_import_id' => $import->id,
                            'source_feature_identifier' => (string) $feature['id'],
                            'error_code' => 'FEATURE_REJECTED',
                            'message' => substr($exception->getMessage(), 0, 500),
                            'context' => 'geometry',
                            'resolution_status' => SpatialFeatureErrorStatus::Open,
                        ]);
                    }
                }
            });

            $import->status = $import->features_rejected > 0
                ? SpatialImportStatus::PartiallyFailed
                : SpatialImportStatus::Imported;
            $import->completed_at = now();
            $import->save();

            $version->import_status = $import->status;
            $version->feature_count = $import->features_imported;
            $version->save();

            $this->logger->info('import_completed', [
                'imported' => $import->features_imported,
                'rejected' => $import->features_rejected,
            ]);
        } catch (Throwable $exception) {
            $import->status = SpatialImportStatus::Failed;
            $import->error_summary = substr($exception->getMessage(), 0, 500);
            $import->completed_at = now();
            $import->save();
            $version->import_status = SpatialImportStatus::Failed;
            $version->save();
            $this->logger->error('import_failed', ['message' => $exception->getMessage()]);
            throw $exception;
        }

        return $import->refresh();
    }

    /**
     * @param  array{id: string, properties: array<string, mixed>, geometry_object: MultipolygonGeometry}  $feature
     * @param  array<string, mixed>  $mapping
     */
    private function importFeature(SpatialDatasetVersion $version, array $feature, array $mapping, ?int $actorId): void
    {
        $properties = $feature['properties'];
        $external = $this->mappedString($properties, $mapping, 'external_identifier') ?: $feature['id'];
        $name = $this->mappedString($properties, $mapping, 'name') ?: 'Unnamed zone '.$external;
        $zoneType = SpatialZoneType::tryFrom($this->mappedString($properties, $mapping, 'zone_type'))
            ?? SpatialZoneType::Other;
        $geometry = $feature['geometry_object'];
        $previousGeometry = null;

        $zone = SpatialZone::query()
            ->where('spatial_dataset_id', $version->spatial_dataset_id)
            ->where('external_identifier', $external)
            ->first();

        if ($zone === null) {
            $zone = SpatialZone::query()->create([
                'spatial_dataset_id' => $version->spatial_dataset_id,
                'external_identifier' => $external,
                'slug' => SpatialSlug::from($name.'-'.$external),
                'zone_type' => $zoneType,
                'jurisdiction_code' => $version->dataset->jurisdiction_code,
                'region_code' => $this->mappedString($properties, $mapping, 'region_code') ?: null,
                'default_name' => $name,
                'description' => $this->mappedString($properties, $mapping, 'description') ?: null,
                'status' => SpatialZoneStatus::Draft,
                'is_fictional' => $version->dataset->is_fictional,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            SpatialZoneTranslation::query()->create([
                'spatial_zone_id' => $zone->id,
                'locale' => 'en',
                'name' => $name,
            ]);
            SpatialZoneTranslation::query()->create([
                'spatial_zone_id' => $zone->id,
                'locale' => 'ka',
                'name' => $name,
            ]);
        } else {
            $latest = $zone->geometryVersions()->orderByDesc('id')->first();
            if ($latest !== null) {
                $previousGeometry = $latest->geometry();
            }
        }

        $report = $this->geometry->validate($geometry, $previousGeometry);
        $bbox = $geometry->boundingBox();
        $centroid = $geometry->centroid();

        $existing = SpatialZoneGeometryVersion::query()
            ->where('spatial_zone_id', $zone->id)
            ->where('spatial_dataset_version_id', $version->id)
            ->first();
        if ($existing !== null) {
            if ($existing->isPublished()) {
                throw SpatialException::versionImmutable();
            }
            $existing->fill([
                'geometry_wkt' => $geometry->toWkt(),
                'min_longitude' => $bbox->west->value,
                'min_latitude' => $bbox->south->value,
                'max_longitude' => $bbox->east->value,
                'max_latitude' => $bbox->north->value,
                'centroid_longitude' => $centroid->longitude->value,
                'centroid_latitude' => $centroid->latitude->value,
                'vertex_count' => $geometry->vertexCount(),
                'polygon_count' => $geometry->polygonCount(),
                'geometry_checksum' => $report['checksum'],
                'source_feature_identifier' => $external,
                'validation_status' => $report['status']->value,
                'validation_warnings' => $report['warnings'],
            ]);
            $existing->save();
            SpatialDriver::persistNativeGeometry($existing->id, $geometry->toWkt());

            return;
        }

        SpatialZoneGeometryVersion::recordCanonical([
            'public_id' => (string) Str::uuid(),
            'spatial_zone_id' => $zone->id,
            'spatial_dataset_version_id' => $version->id,
            'geometry_wkt' => $geometry->toWkt(),
            'min_longitude' => $bbox->west->value,
            'min_latitude' => $bbox->south->value,
            'max_longitude' => $bbox->east->value,
            'max_latitude' => $bbox->north->value,
            'centroid_longitude' => $centroid->longitude->value,
            'centroid_latitude' => $centroid->latitude->value,
            'vertex_count' => $geometry->vertexCount(),
            'polygon_count' => $geometry->polygonCount(),
            'geometry_checksum' => $report['checksum'],
            'source_feature_identifier' => $external,
            'effective_from' => $version->effective_from,
            'effective_until' => $version->effective_until,
            'status' => SpatialGeometryVersionStatus::Draft->value,
            'validation_status' => $report['status'] === SpatialValidationStatus::Valid
                ? SpatialValidationStatus::Valid->value
                : SpatialValidationStatus::Warning->value,
            'validation_warnings' => $report['warnings'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $geometry);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $mapping
     */
    private function mappedString(array $properties, array $mapping, string $field): string
    {
        $key = $mapping[$field] ?? null;
        if (! is_string($key) || $key === '') {
            return '';
        }
        $value = $properties[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
