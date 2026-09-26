<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Enums\SpatialZoneStatus;
use App\Domains\Geography\Enums\SpatialZoneType;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test-only fictional zone. Never use in production seeds.
 *
 * @extends Factory<SpatialZone>
 */
class SpatialZoneFactory extends Factory
{
    protected $model = SpatialZone::class;

    public function definition(): array
    {
        $name = 'FICTIONAL Test Zone '.$this->faker->unique()->numerify('###');

        return [
            'public_id' => (string) Str::uuid(),
            'spatial_dataset_id' => SpatialDataset::factory(),
            'external_identifier' => 'TEST-'.$this->faker->unique()->numerify('####'),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'zone_type' => SpatialZoneType::ProtectedArea,
            'jurisdiction_code' => 'XX',
            'default_name' => $name,
            'description' => 'FICTIONAL test polygon. Not an official protected area.',
            'status' => SpatialZoneStatus::Draft,
            'is_fictional' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => SpatialZoneStatus::Active,
        ]);
    }
}
