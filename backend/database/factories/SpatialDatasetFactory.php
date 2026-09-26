<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Enums\SpatialDatasetStatus;
use App\Domains\Geography\Enums\SpatialDatasetType;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test-only fictional spatial dataset. Never use in production seeds.
 *
 * @extends Factory<SpatialDataset>
 */
class SpatialDatasetFactory extends Factory
{
    protected $model = SpatialDataset::class;

    public function definition(): array
    {
        $name = 'FICTIONAL Test Dataset '.$this->faker->unique()->numerify('###');

        return [
            'public_id' => (string) Str::uuid(),
            'spatial_source_id' => SpatialSource::factory()->verified(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'dataset_type' => SpatialDatasetType::ProtectedAreas,
            'jurisdiction_code' => 'XX',
            'description' => 'FICTIONAL polygons for automated tests only.',
            'native_crs' => 'EPSG:4326',
            'canonical_srid' => 4326,
            'status' => SpatialDatasetStatus::Draft,
            'is_fictional' => true,
        ];
    }
}
