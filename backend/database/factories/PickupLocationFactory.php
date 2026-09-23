<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Checkout\Models\PickupLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PickupLocation>
 */
class PickupLocationFactory extends Factory
{
    protected $model = PickupLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'code' => 'pickup-'.strtolower(Str::random(6)),
            'name_translations' => ['ka' => 'მაღაზია', 'en' => 'Store'],
            'address_translations' => ['ka' => 'თბილისი', 'en' => 'Tbilisi'],
            'phone' => null,
            'working_hours_translations' => null,
            'instructions_translations' => null,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
