<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Checkout\Enums\FulfillmentMethodType;
use App\Domains\Checkout\Models\DeliveryRateRule;
use App\Domains\Checkout\Models\DeliveryZone;
use App\Domains\Checkout\Models\FulfillmentMethod;
use App\Domains\Checkout\Models\PickupLocation;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

final class CheckoutFixtures
{
    public static function seedFulfillment(): void
    {
        if (FulfillmentMethod::query()->where('code', 'local_delivery')->exists()) {
            return;
        }

        $pickup = PickupLocation::factory()->create([
            'code' => 'tbilisi-store',
            'name_translations' => ['ka' => 'თბილისის მაღაზია', 'en' => 'Tbilisi store'],
            'address_translations' => ['ka' => 'თბილისი', 'en' => 'Tbilisi'],
        ]);

        FulfillmentMethod::factory()->pickup()->create([
            'code' => 'store_pickup',
            'name_translations' => ['ka' => 'მაღაზიიდან გატანა', 'en' => 'Store pickup'],
        ]);

        $local = FulfillmentMethod::factory()->create([
            'code' => 'local_delivery',
            'type' => FulfillmentMethodType::LocalDelivery,
            'base_price_minor' => 500,
            'free_above_minor' => 20000,
            'sort_order' => 2,
        ]);

        $courier = FulfillmentMethod::factory()->create([
            'code' => 'courier_delivery',
            'type' => FulfillmentMethodType::CourierDelivery,
            'base_price_minor' => 1500,
            'free_above_minor' => null,
            'sort_order' => 3,
        ]);

        $tbilisi = DeliveryZone::factory()->create([
            'code' => 'ge-tbilisi',
            'municipality_or_city' => 'Tbilisi',
            'priority' => 10,
            'pickup_location_id' => $pickup->id,
        ]);

        $georgia = DeliveryZone::factory()->create([
            'code' => 'ge-country',
            'municipality_or_city' => null,
            'priority' => 100,
        ]);

        DeliveryRateRule::factory()->create([
            'fulfillment_method_id' => $local->id,
            'delivery_zone_id' => $tbilisi->id,
            'price_minor' => 500,
            'free_above_minor' => 20000,
            'priority' => 10,
        ]);

        DeliveryRateRule::factory()->create([
            'fulfillment_method_id' => $courier->id,
            'delivery_zone_id' => $georgia->id,
            'price_minor' => 1500,
            'free_above_minor' => null,
            'priority' => 100,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function headers(string $key): array
    {
        return [
            'Idempotency-Key' => $key,
            'Accept' => 'application/json',
            'X-Locale' => 'ka',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function contact(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'ნინო',
            'last_name' => 'ბერიძე',
            'email' => 'nino@example.com',
            'phone' => '555123456',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function tbilisiAddress(array $overrides = []): array
    {
        return array_merge([
            'recipient_first_name' => 'ნინო',
            'recipient_last_name' => 'ბერიძე',
            'phone' => '555123456',
            'country_code' => 'GE',
            'municipality_or_city' => 'Tbilisi',
            'street' => 'Rustaveli',
            'house_number' => '10',
        ], $overrides);
    }

    public static function newKey(): string
    {
        return (string) Str::uuid();
    }

    public static function sessionId(TestResponse $response): string
    {
        return (string) $response->json('data.checkout_session.id');
    }
}
