<?php

declare(strict_types=1);

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Models\PaymentAttempt;
use Tests\Support\PaymentFixtures;
use Tests\Support\PublicCatalogFixtures;

it('lists only the test method for a payable order', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'pm1');

    $response = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->getJson('/api/v1/payment-methods?order_id='.$pending['order_id']);

    $response->assertOk()
        ->assertJsonPath('data.0.code', 'test_hosted_redirect')
        ->assertJsonPath('data.0.development_only', true)
        ->assertJsonMissingPath('data.0.secret');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('creates a payment attempt from the stored order amount and redirects', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'pa1');

    $response = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'pay-create-1', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
            'amount' => 1,
        ]);

    $response->assertStatus(422);

    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'pay-create-1b', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ]);

    $created->assertCreated()
        ->assertJsonPath('data.status', 'requires_action')
        ->assertJsonPath('data.amount.amount_minor', $pending['grand_total_minor'])
        ->assertJsonPath('data.amount.currency', $pending['currency'])
        ->assertJsonPath('data.action.type', 'redirect')
        ->assertJsonPath('data.order.status', 'payment_processing')
        ->assertJsonPath('data.order.payment_status', 'pending');
    expect($created->json('data.action.url'))->toContain('/payment/test/')
        ->and($created->json('data.id'))->not->toMatch('/^\d+$/');

    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->status)->toBe(OrderStatus::PaymentProcessing)
        ->and($order?->payment_status)->toBe(PaymentStatus::Pending)
        ->and(PaymentAttempt::query()->where('order_id', $order?->id)->count())->toBe(1);
});

it('replays the same idempotency key and conflicts on a different payload', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'pa2');

    $first = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'same-key', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    $replay = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'same-key', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ]);
    $replay->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));

    test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'same-key', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'missing',
        ])->assertStatus(409);
});

it('returns the active attempt instead of creating a second one', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'pa3');

    $first = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'a1', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    $second = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'a2', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ]);

    $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
    expect(PaymentAttempt::query()->count())->toBe(1);
});

it('marks a provider timeout as unknown and keeps the attempt', function (): void {
    config()->set('payments.test.scenario', 'timeout');
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'pa4');

    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'to1', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ]);

    $created->assertCreated()->assertJsonPath('data.status', 'unknown');
    expect(PaymentAttempt::query()->where('status', PaymentAttemptStatus::Unknown->value)->count())->toBe(1);
});

it('rejects payment on cancelled, expired, and already paid orders', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'pa5');

    test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'cancel-ord', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/cancel')
        ->assertOk();

    test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'after-cancel', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertStatus(422);
});

it('requires an idempotency key', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'pa6');

    test()->flushHeaders();
    test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'PAYMENT_IDEMPOTENCY_REQUIRED');
});

it('does not create a payment when reservations are missing', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'pa7');
    InventoryReservation::query()->where('reference_id', $pending['order_id'])->update([
        'status' => InventoryReservationStatus::Released->value,
    ]);

    test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'no-res', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'PAYMENT_RESERVATION_MISSING');
});
