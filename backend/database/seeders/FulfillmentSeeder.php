<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Checkout\Enums\FulfillmentMethodType;
use App\Domains\Checkout\Models\DeliveryRateRule;
use App\Domains\Checkout\Models\DeliveryZone;
use App\Domains\Checkout\Models\FulfillmentMethod;
use App\Domains\Checkout\Models\PickupLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class FulfillmentSeeder extends Seeder
{
    public function run(): void
    {
        $pickup = PickupLocation::query()->updateOrCreate(
            ['code' => 'tbilisi-store'],
            [
                'public_id' => (string) Str::uuid(),
                'name_translations' => [
                    'ka' => 'თბილისის მაღაზია',
                    'en' => 'Tbilisi store',
                ],
                'address_translations' => [
                    'ka' => 'თბილისი',
                    'en' => 'Tbilisi',
                ],
                'phone' => null,
                'working_hours_translations' => null,
                'instructions_translations' => [
                    'ka' => 'გატანა შესაძლებელია მხოლოდ აქტიური კვოტის ვადაში.',
                    'en' => 'Pickup is available while the quote is active.',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $pickupMethod = FulfillmentMethod::query()->updateOrCreate(
            ['code' => 'store_pickup'],
            [
                'public_id' => (string) Str::uuid(),
                'type' => FulfillmentMethodType::StorePickup,
                'name_translations' => [
                    'ka' => 'მაღაზიიდან გატანა',
                    'en' => 'Store pickup',
                ],
                'description_translations' => [
                    'ka' => 'გაიტანე შეკვეთა კონფიგურირებული მაღაზიიდან.',
                    'en' => 'Collect from a configured store location.',
                ],
                'is_active' => true,
                'currency' => 'GEL',
                'base_price_minor' => 0,
                'free_above_minor' => null,
                'estimated_min_days' => null,
                'estimated_max_days' => null,
                'sort_order' => 1,
                'requires_address' => false,
            ],
        );

        $local = FulfillmentMethod::query()->updateOrCreate(
            ['code' => 'local_delivery'],
            [
                'public_id' => (string) Str::uuid(),
                'type' => FulfillmentMethodType::LocalDelivery,
                'name_translations' => [
                    'ka' => 'ადგილობრივი მიწოდება',
                    'en' => 'Local delivery',
                ],
                'description_translations' => [
                    'ka' => 'მიწოდება კონფიგურირებულ ზონაში.',
                    'en' => 'Delivery inside a configured zone.',
                ],
                'is_active' => true,
                'currency' => 'GEL',
                'base_price_minor' => 500,
                'free_above_minor' => 20000,
                'estimated_min_days' => null,
                'estimated_max_days' => null,
                'sort_order' => 2,
                'requires_address' => true,
            ],
        );

        $courier = FulfillmentMethod::query()->updateOrCreate(
            ['code' => 'courier_delivery'],
            [
                'public_id' => (string) Str::uuid(),
                'type' => FulfillmentMethodType::CourierDelivery,
                'name_translations' => [
                    'ka' => 'საკურიერო მიწოდება',
                    'en' => 'Courier delivery',
                ],
                'description_translations' => [
                    'ka' => 'მიწოდება კონფიგურირებული ქვეყნის ზონაში.',
                    'en' => 'Delivery inside a configured country zone.',
                ],
                'is_active' => true,
                'currency' => 'GEL',
                'base_price_minor' => 1500,
                'free_above_minor' => null,
                'estimated_min_days' => null,
                'estimated_max_days' => null,
                'sort_order' => 3,
                'requires_address' => true,
            ],
        );

        $tbilisi = DeliveryZone::query()->updateOrCreate(
            ['code' => 'ge-tbilisi'],
            [
                'public_id' => (string) Str::uuid(),
                'name_translations' => ['ka' => 'თბილისი', 'en' => 'Tbilisi'],
                'country_code' => 'GE',
                'region' => null,
                'municipality_or_city' => 'Tbilisi',
                'postal_code_pattern' => null,
                'pickup_location_id' => $pickup->id,
                'is_active' => true,
                'priority' => 10,
            ],
        );

        $georgia = DeliveryZone::query()->updateOrCreate(
            ['code' => 'ge-country'],
            [
                'public_id' => (string) Str::uuid(),
                'name_translations' => ['ka' => 'საქართველო', 'en' => 'Georgia'],
                'country_code' => 'GE',
                'region' => null,
                'municipality_or_city' => null,
                'postal_code_pattern' => null,
                'pickup_location_id' => null,
                'is_active' => true,
                'priority' => 100,
            ],
        );

        DeliveryRateRule::query()->updateOrCreate(
            [
                'fulfillment_method_id' => $local->id,
                'delivery_zone_id' => $tbilisi->id,
            ],
            [
                'minimum_subtotal_minor' => null,
                'maximum_subtotal_minor' => null,
                'price_minor' => 500,
                'free_above_minor' => 20000,
                'priority' => 10,
                'is_active' => true,
            ],
        );

        DeliveryRateRule::query()->updateOrCreate(
            [
                'fulfillment_method_id' => $courier->id,
                'delivery_zone_id' => $georgia->id,
            ],
            [
                'minimum_subtotal_minor' => null,
                'maximum_subtotal_minor' => null,
                'price_minor' => 1500,
                'free_above_minor' => null,
                'priority' => 100,
                'is_active' => true,
            ],
        );

        unset($pickupMethod);
    }
}
