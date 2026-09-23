<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Checkout\Enums\FulfillmentMethodType;
use App\Domains\Checkout\Models\FulfillmentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FulfillmentMethod>
 */
class FulfillmentMethodFactory extends Factory
{
    protected $model = FulfillmentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'type' => FulfillmentMethodType::LocalDelivery,
            'code' => 'method-'.strtolower(Str::random(6)),
            'name_translations' => ['ka' => 'მიწოდება', 'en' => 'Delivery'],
            'description_translations' => ['ka' => 'კონფიგურირებული მიწოდება', 'en' => 'Configured delivery'],
            'is_active' => true,
            'currency' => 'GEL',
            'base_price_minor' => 500,
            'free_above_minor' => null,
            'estimated_min_days' => null,
            'estimated_max_days' => null,
            'sort_order' => 1,
            'requires_address' => true,
        ];
    }

    public function pickup(): static
    {
        return $this->state(fn (): array => [
            'type' => FulfillmentMethodType::StorePickup,
            'code' => 'store_pickup',
            'requires_address' => false,
            'base_price_minor' => 0,
        ]);
    }
}
