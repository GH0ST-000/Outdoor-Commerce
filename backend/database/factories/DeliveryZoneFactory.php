<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Checkout\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'code' => 'zone-'.strtolower(Str::random(6)),
            'name_translations' => ['ka' => 'თბილისი', 'en' => 'Tbilisi'],
            'country_code' => 'GE',
            'region' => null,
            'municipality_or_city' => 'Tbilisi',
            'postal_code_pattern' => null,
            'pickup_location_id' => null,
            'is_active' => true,
            'priority' => 10,
        ];
    }
}
