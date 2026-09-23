<?php

declare(strict_types=1);

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Models\Order;
use Tests\Support\OrderFixtures;
use Tests\Support\PublicCatalogFixtures;

it('creates only one order for concurrent requests on the same quote', function (): void {
    if ((string) config('database.default') === 'sqlite') {
        test()->markTestSkipped('SQLite cannot express the MySQL row-lock duplicate-order race.');
    }

    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'race');
    $payload = [
        'checkout_session_id' => $quoted['session_id'],
        'quote_id' => $quoted['quote_id'],
        'checkout_version' => $quoted['checkout_version'],
    ];

    $first = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('race-a'))
        ->postJson('/api/v1/orders', $payload);
    $second = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('race-b'))
        ->postJson('/api/v1/orders', $payload);

    expect(in_array($first->status(), [200, 201], true))->toBeTrue()
        ->and(in_array($second->status(), [200, 201], true))->toBeTrue()
        ->and($first->json('data.id'))->toBe($second->json('data.id'))
        ->and(Order::query()->count())->toBe(1)
        ->and(InventoryReservation::query()->where('status', InventoryReservationStatus::Active)->count())->toBe(1);
});

it('holds the last unit after the quoted guest confirms the order', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 1, 'price' => 10000]);
    $quotedA = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'osa');

    $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quotedA['cookie'])
        ->withHeaders(OrderFixtures::headers('osa-o'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quotedA['session_id'],
            'quote_id' => $quotedA['quote_id'],
            'checkout_version' => $quotedA['checkout_version'],
        ])->assertCreated();

    expect(Order::query()->count())->toBe(1)
        ->and((int) InventoryReservation::query()->where('reference_id', $created->json('data.id'))->where('status', InventoryReservationStatus::Active)->sum('quantity'))->toBe(1);
});
