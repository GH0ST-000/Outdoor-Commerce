<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Enums\SpatialImportStatus;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialImport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpatialImport>
 */
class SpatialImportFactory extends Factory
{
    protected $model = SpatialImport::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'spatial_dataset_version_id' => SpatialDatasetVersion::factory(),
            'status' => SpatialImportStatus::Pending,
        ];
    }
}
