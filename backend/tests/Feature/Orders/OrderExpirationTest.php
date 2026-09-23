<?php

declare(strict_types=1);

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use Tests\Support\OrderFixtures;
use Tests\Support\PublicCatalogFixtures;

it('expires an overdue unpaid order and releases reservations', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'ex1');

    $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('ex1-c'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertCreated();

    $orderId = (string) $created->json('data.id');
    Order::query()->where('public_id', $orderId)->update([
        'reservation_expires_at' => now()->subMinute(),
    ]);

    $this->artisan('orders:expire-unpaid')
        ->expectsOutputToContain('Expired 1 unpaid order')
        ->assertSuccessful();

    $order = Order::query()->where('public_id', $orderId)->first();
    expect($order?->status)->toBe(OrderStatus::Expired)
        ->and(InventoryReservation::query()->where('reference_id', $orderId)->where('status', InventoryReservationStatus::Active)->count())->toBe(0);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();
    expect(Order::query()->where('public_id', $orderId)->first()?->status)->toBe(OrderStatus::Expired);
});

it('does not expire a recently created unpaid order', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'ex2');

    $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('ex2-c'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertCreated();

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect(Order::query()->where('public_id', $created->json('data.id'))->first()?->status)
        ->toBe(OrderStatus::PendingPayment);
});

it('enforces expiration on request', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'ex3');

    $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('ex3-c'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertCreated();

    $orderId = (string) $created->json('data.id');
    $orderCookie = OrderFixtures::guestOrderCookie($created);
    Order::query()->where('public_id', $orderId)->update([
        'reservation_expires_at' => now()->subMinute(),
    ]);

    test()->withUnencryptedCookie((string) config('order.cookie.name'), (string) $orderCookie)
        ->getJson('/api/v1/orders/'.$orderId)
        ->assertOk()
        ->assertJsonPath('data.status', 'expired')
        ->assertJsonPath('data.can_cancel', false);
});

it('does not expire a cancelled order again', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'ex4');

    $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('ex4-c'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertCreated();

    $orderId = (string) $created->json('data.id');
    $orderCookie = OrderFixtures::guestOrderCookie($created);

    test()->withUnencryptedCookie((string) config('order.cookie.name'), (string) $orderCookie)
        ->withHeaders(OrderFixtures::headers('ex4-x'))
        ->postJson('/api/v1/orders/'.$orderId.'/cancel')
        ->assertOk();

    Order::query()->where('public_id', $orderId)->update([
        'reservation_expires_at' => now()->subMinute(),
    ]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();
    expect(Order::query()->where('public_id', $orderId)->first()?->status)->toBe(OrderStatus::Cancelled);
});
