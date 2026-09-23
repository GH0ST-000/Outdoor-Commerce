<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Checkout\Models\DeliveryRateRule;
use App\Domains\Checkout\Models\DeliveryZone;
use App\Domains\Checkout\Models\FulfillmentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRateRule>
 */
class DeliveryRateRuleFactory extends Factory
{
    protected $model = DeliveryRateRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fulfillment_method_id' => FulfillmentMethod::factory(),
            'delivery_zone_id' => DeliveryZone::factory(),
            'minimum_subtotal_minor' => null,
            'maximum_subtotal_minor' => null,
            'price_minor' => 500,
            'free_above_minor' => 20000,
            'priority' => 10,
            'is_active' => true,
        ];
    }
}
