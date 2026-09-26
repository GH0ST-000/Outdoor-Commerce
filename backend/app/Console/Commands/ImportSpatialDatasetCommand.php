<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Geography\Enums\SpatialDatasetStatus;
use App\Domains\Geography\Enums\SpatialDatasetType;
use App\Domains\Geography\Enums\SpatialSourceType;
use App\Domains\Geography\Enums\SpatialVerificationStatus;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialSource;
use App\Domains\Geography\Services\GeoJsonValidator;
use App\Domains\Geography\Services\SpatialDatasetWriteService;
use App\Domains\Geography\Services\SpatialImportService;
use App\Domains\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Throwable;

final class ImportSpatialDatasetCommand extends Command
{
    protected $signature = 'spatial:import
        {--source= : GeoJSON file path}
        {--dataset= : Dataset slug}
        {--version-label= : Version label. Artisan reserves --version.}
        {--source-code= : Official layer code such as APA17}
        {--identifier-property= : Feature property used as the stable identifier}
        {--name-property= : Feature property used as the display name}
        {--zone-type-property= : Feature property that already contains a spatial zone type}
        {--description-property= : Feature property stored as the zone description}
        {--actor= : Administrator user id}
        {--dry-run : Validate GeoJSON and print property keys without writing}';

    protected $description = 'Validate a GeoJSON file and import it as a draft spatial version. Publication stays manual.';

    public function handle(
        GeoJsonValidator $geojson,
        SpatialDatasetWriteService $datasets,
        SpatialImportService $imports,
    ): int {
        $path = (string) $this->option('source');
        $code = (string) $this->option('source-code');
        $slug = (string) $this->option('dataset');
        $versionLabel = (string) $this->option('version-label');
        if ($path === '' || $code === '' || $slug === '' || $versionLabel === '') {
            $this->error('source, dataset, version-label, and source-code are required.');

            return self::FAILURE;
        }
        if (! is_file($path)) {
            $this->missingFile($code);

            return self::FAILURE;
        }

        try {
            $contents = (string) file_get_contents($path);
            $parsed = $geojson->parse($contents, 'EPSG:4326');
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $checksum = hash('sha256', $contents);
        $this->line('checksum: '.$checksum);
        $this->line('features: '.$parsed['feature_count']);
        $this->line('properties: '.implode(', ', $parsed['property_keys']));
        $this->line('crs: EPSG:4326');

        $identifier = (string) $this->option('identifier-property');
        $name = (string) $this->option('name-property');
        if ((bool) $this->option('dry-run')) {
            $this->info('Dry run did not write. Pass --identifier-property and --name-property to import.');

            return self::SUCCESS;
        }
        if ($identifier === '' || $name === '') {
            $this->error('identifier-property and name-property are required so features are not given synthetic ids.');

            return self::FAILURE;
        }
        foreach ($parsed['features'] as $feature) {
            $value = $feature['properties'][$identifier] ?? null;
            if (! is_scalar($value) || trim((string) $value) === '') {
                $this->error('Feature '.$feature['id'].' has no stable '.$identifier.' value. Import aborted.');

                return self::FAILURE;
            }
        }

        $actor = User::query()->find((int) $this->option('actor'));
        if (! $actor instanceof User) {
            $this->error('An existing administrator id is required in --actor.');

            return self::FAILURE;
        }

        try {
            $dataset = $this->dataset($code, $slug, $actor);
            $existing = SpatialDatasetVersion::query()
                ->where('spatial_dataset_id', $dataset->id)
                ->where('content_checksum', $checksum)
                ->first();
            if ($existing instanceof SpatialDatasetVersion) {
                $this->info('Skipped. This checksum is already stored as a draft or later version.');

                return self::SUCCESS;
            }

            $file = new UploadedFile($path, basename($path), 'application/geo+json', \UPLOAD_ERR_OK, true);
            $version = $datasets->uploadVersion($dataset, $file, [
                'version_label' => $versionLabel,
                'source_crs' => 'EPSG:4326',
                'source_url' => $dataset->source->official_url,
                'effective_from' => now(),
            ], $actor);
            $mapping = [
                'external_identifier' => $identifier,
                'name' => $name,
            ];
            $zoneType = (string) $this->option('zone-type-property');
            $description = (string) $this->option('description-property');
            if ($zoneType !== '') {
                $mapping['zone_type'] = $zoneType;
            }
            if ($description !== '') {
                $mapping['description'] = $description;
            }
            $datasets->saveMapping($version, $mapping, $actor);
            $import = $imports->start($version, $actor);
            $imports->run($import);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $version->refresh();
        if ($version->review_status->value !== 'draft') {
            $this->error('Import changed review status. Publication is not allowed from this command.');

            return self::FAILURE;
        }
        $this->info('Draft import stored. It is not published.');

        return self::SUCCESS;
    }

    private function dataset(string $code, string $slug, User $actor): SpatialDataset
    {
        $catalogue = $this->catalogueRecord($code);
        $official = $catalogue !== null;
        $source = SpatialSource::query()->where('official_identifier', $code)->first();
        if (! $source instanceof SpatialSource) {
            $source = SpatialSource::query()->create([
                'name' => $official ? (string) $catalogue['name_ka'] : 'Fictional spatial source '.$code,
                'slug' => 'ge-gis-'.strtolower($code),
                'source_type' => SpatialSourceType::OfficialGeoportal,
                'official_url' => $official ? (string) $catalogue['detail_url'] : null,
                'official_identifier' => $code,
                'publisher_name' => $official ? (string) $catalogue['publisher'] : 'Test fixture',
                'jurisdiction_code' => 'GE',
                'verification_status' => SpatialVerificationStatus::PendingReview,
                'is_active' => true,
                'is_fictional' => ! $official,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        }

        $dataset = SpatialDataset::query()->where('spatial_source_id', $source->id)->where('slug', $slug)->first();
        if ($dataset instanceof SpatialDataset) {
            return $dataset->load('source');
        }

        $created = SpatialDataset::query()->create([
            'spatial_source_id' => $source->id,
            'name' => $official ? (string) $catalogue['name_ka'] : 'Fictional dataset '.$slug,
            'slug' => $slug,
            'dataset_type' => $official
                ? SpatialDatasetType::from((string) $catalogue['dataset_type'])
                : SpatialDatasetType::Other,
            'jurisdiction_code' => 'GE',
            'description' => $official ? (string) $catalogue['layer_name'] : 'Not an official Georgian boundary.',
            'native_crs' => 'EPSG:4326',
            'canonical_srid' => 4326,
            'status' => SpatialDatasetStatus::Draft,
            'is_fictional' => ! $official,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return $created->load('source');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function catalogueRecord(string $code): ?array
    {
        $path = base_path('database/data/official/georgia/spatial/datasets.json');
        if (! is_file($path)) {
            return null;
        }
        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload)) {
            return null;
        }
        foreach ($payload['records'] ?? [] as $record) {
            if (is_array($record) && ($record['source_code'] ?? '') === $code) {
                return $record;
            }
        }

        return null;
    }

    private function missingFile(string $code): void
    {
        $this->error('GeoJSON file is missing. No geometry was invented.');
        $record = $this->catalogueRecord($code);
        if ($record === null) {
            return;
        }
        $this->line((string) $record['source_code'].' '.(string) $record['layer_name']);
        $this->line('Catalogue: https://portal.mepa.gov.ge/Ge/User/data');
        $this->line('Detail: '.(string) $record['detail_url']);
        $this->line('Map: '.(string) $record['map_url']);
        $this->line('The detail page has no file download. Export the layer from the map UI, save EPSG:4326 GeoJSON, then rerun this command.');
    }
}
