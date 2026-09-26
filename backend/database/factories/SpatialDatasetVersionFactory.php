<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Enums\SpatialImportStatus;
use App\Domains\Geography\Enums\SpatialReviewStatus;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test-only fictional dataset version. Never use in production seeds.
 *
 * @extends Factory<SpatialDatasetVersion>
 */
class SpatialDatasetVersionFactory extends Factory
{
    protected $model = SpatialDatasetVersion::class;

    public function definition(): array
    {
        $body = '{"type":"FeatureCollection","features":[]}';

        return [
            'public_id' => (string) Str::uuid(),
            'spatial_dataset_id' => SpatialDataset::factory(),
            'version_label' => 'v-'.Str::lower(Str::random(6)),
            'effective_from' => now()->subYear(),
            'retrieved_at' => now(),
            'content_checksum' => hash('sha256', $body.Str::uuid()),
            'checksum_algorithm' => 'sha256',
            'storage_disk' => 'spatial_private',
            'storage_path' => 'test/'.Str::uuid().'.geojson',
            'original_filename' => 'fictional.geojson',
            'mime_type' => 'application/geo+json',
            'file_size' => strlen($body),
            'source_crs' => 'EPSG:4326',
            'import_status' => SpatialImportStatus::Pending,
            'review_status' => SpatialReviewStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'import_status' => SpatialImportStatus::Imported,
            'review_status' => SpatialReviewStatus::Published,
            'published_at' => now(),
        ]);
    }
}
