<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Enums\SpatialSourceType;
use App\Domains\Geography\Enums\SpatialVerificationStatus;
use App\Domains\Geography\Models\SpatialSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test-only fictional spatial source. Never use in production seeds.
 *
 * @extends Factory<SpatialSource>
 */
class SpatialSourceFactory extends Factory
{
    protected $model = SpatialSource::class;

    public function definition(): array
    {
        $name = 'FICTIONAL Test Spatial Source '.$this->faker->unique()->numerify('###');

        return [
            'public_id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'source_type' => SpatialSourceType::ManualVerifiedDataset,
            'publisher_name' => 'FICTIONAL Test Publisher',
            'jurisdiction_code' => 'XX',
            'attribution_text' => 'FICTIONAL test dataset. Not an official boundary.',
            'verification_status' => SpatialVerificationStatus::Unverified,
            'is_active' => true,
            'is_fictional' => true,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'verification_status' => SpatialVerificationStatus::Verified,
            'verified_at' => now(),
        ]);
    }
}
