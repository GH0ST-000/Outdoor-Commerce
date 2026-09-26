<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\Enums\SpatialDatasetStatus;
use App\Domains\Geography\Enums\SpatialDatasetType;
use App\Domains\Geography\Enums\SpatialImportStatus;
use App\Domains\Geography\Enums\SpatialReviewStatus;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Support\SpatialSlug;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Http\UploadedFile;

final class SpatialDatasetWriteService
{
    public function __construct(
        private readonly SpatialAuditRecorder $audit,
        private readonly SpatialFileIngestionService $files,
        private readonly SpatialSourceWriteService $sources,
        private readonly GeoJsonValidator $geojson,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createDataset(array $payload, User $actor): SpatialDataset
    {
        $dataset = SpatialDataset::query()->create([
            'spatial_source_id' => $payload['spatial_source_id'],
            'name' => $payload['name'],
            'slug' => SpatialSlug::from((string) $payload['name']),
            'dataset_type' => SpatialDatasetType::from((string) $payload['dataset_type']),
            'jurisdiction_code' => $payload['jurisdiction_code'] ?? config('spatial.default_jurisdiction'),
            'description' => $payload['description'] ?? null,
            'native_crs' => $payload['native_crs'] ?? 'EPSG:4326',
            'canonical_srid' => 4326,
            'update_frequency' => $payload['update_frequency'] ?? null,
            'status' => SpatialDatasetStatus::Draft,
            'is_fictional' => (bool) ($payload['is_fictional'] ?? false),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->audit->record(AuditEvent::SpatialDatasetCreated, $actor, 'spatial_dataset', $dataset->public_id);

        return $dataset;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateDataset(SpatialDataset $dataset, array $payload, User $actor): SpatialDataset
    {
        $dataset->fill($payload);
        $dataset->updated_by = $actor->id;
        $dataset->save();
        $this->audit->record(AuditEvent::SpatialDatasetUpdated, $actor, 'spatial_dataset', $dataset->public_id);

        return $dataset->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function uploadVersion(SpatialDataset $dataset, UploadedFile $file, array $payload, User $actor): SpatialDatasetVersion
    {
        $stored = $this->files->storeUpload($file);
        if (SpatialDatasetVersion::query()
            ->where('spatial_dataset_id', $dataset->id)
            ->where('content_checksum', $stored['checksum'])
            ->exists()) {
            throw SpatialException::checksumConflict();
        }

        $crs = (string) ($payload['source_crs'] ?? '');
        $this->geojson->assertCrs($crs);
        $contents = $this->files->read($stored['disk'], $stored['path']);
        $preview = $this->geojson->parse($contents, $crs);

        $version = SpatialDatasetVersion::query()->create([
            'spatial_dataset_id' => $dataset->id,
            'version_label' => (string) $payload['version_label'],
            'source_url' => $payload['source_url'] ?? null,
            'source_published_at' => $payload['source_published_at'] ?? null,
            'effective_from' => $payload['effective_from'] ?? now(),
            'effective_until' => $payload['effective_until'] ?? null,
            'retrieved_at' => now(),
            'retrieved_by' => $actor->id,
            'content_checksum' => $stored['checksum'],
            'checksum_algorithm' => 'sha256',
            'storage_disk' => $stored['disk'],
            'storage_path' => $stored['path'],
            'original_filename' => $stored['original'],
            'mime_type' => $stored['mime'],
            'file_size' => $stored['size'],
            'feature_count' => $preview['feature_count'],
            'source_crs' => $crs,
            'import_status' => SpatialImportStatus::Validated,
            'review_status' => SpatialReviewStatus::Draft,
            'validation_summary' => [
                'feature_count' => $preview['feature_count'],
                'vertex_count' => $preview['vertex_count'],
                'geometry_types' => $preview['geometry_types'],
                'property_keys' => $preview['property_keys'],
                'bounds' => $preview['bounds'],
                'warnings' => $preview['warnings'],
            ],
        ]);

        $this->audit->record(AuditEvent::SpatialDatasetVersionUploaded, $actor, 'spatial_dataset_version', $version->public_id, null, [
            'checksum' => $version->content_checksum,
            'feature_count' => $version->feature_count,
        ]);

        return $version;
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    public function saveMapping(SpatialDatasetVersion $version, array $mapping, User $actor): SpatialDatasetVersion
    {
        if ($version->isImmutable()) {
            throw SpatialException::versionImmutable();
        }
        $allowed = ['external_identifier', 'name', 'zone_type', 'region_code', 'description', 'effective_from', 'effective_until'];
        foreach (array_keys($mapping) as $field) {
            if (! in_array($field, $allowed, true)) {
                throw SpatialException::mappingInvalid('Mapping field is not allowed: '.$field);
            }
        }
        $version->property_mapping = $mapping;
        $version->save();
        $this->audit->record(AuditEvent::SpatialPropertyMappingSaved, $actor, 'spatial_dataset_version', $version->public_id);

        return $version->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(SpatialDatasetVersion $version): array
    {
        $contents = $this->files->read($version->storage_disk, $version->storage_path);
        $parsed = $this->geojson->parse($contents, (string) $version->source_crs);
        $limit = (int) config('spatial.import.preview_features', 8);
        $sample = [];
        foreach (array_slice($parsed['features'], 0, $limit) as $feature) {
            $sample[] = [
                'id' => $feature['id'],
                'properties' => $feature['properties'],
                'geometry_type' => $feature['geometry']['type'],
                'vertex_count' => $feature['geometry_object']->vertexCount(),
                'bounding_box' => $feature['geometry_object']->boundingBox()->toArray(),
            ];
        }

        return [
            'feature_count' => $parsed['feature_count'],
            'vertex_count' => $parsed['vertex_count'],
            'property_keys' => $parsed['property_keys'],
            'bounds' => $parsed['bounds'],
            'geometry_types' => $parsed['geometry_types'],
            'warnings' => $parsed['warnings'],
            'sample' => $sample,
            'mapping' => $version->property_mapping,
            'source_crs' => $version->source_crs,
            'srid' => 4326,
            'coordinate_order' => 'longitude,latitude',
        ];
    }

    public function assertSourceReady(SpatialDataset $dataset): void
    {
        $dataset->loadMissing('source');
        $source = $dataset->source ?? throw SpatialException::notFound('Spatial source');
        $this->sources->assertVerified($source);
        if ((int) $dataset->canonical_srid !== 4326) {
            throw SpatialException::publicationInvalid(['canonical_srid' => $dataset->canonical_srid]);
        }
    }
}
