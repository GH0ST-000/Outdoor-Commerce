<?php

declare(strict_types=1);

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentWebhookProcessingStatus;
use App\Domains\Payments\Events\InventoryCommittedForOrder;
use App\Domains\Payments\Events\PaymentSucceeded;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentWebhook;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Support\PaymentFixtures;
use Tests\Support\PublicCatalogFixtures;

function deliverSuccessWebhook(string $attemptPublicId, array $overrides = []): TestResponse
{
    $attempt = PaymentAttempt::query()->where('public_id', $attemptPublicId)->firstOrFail();
    $signed = PaymentFixtures::signedWebhook(array_merge([
        'event_id' => 'evt_'.$attempt->public_id,
        'payment_id' => $attempt->provider_payment_id,
        'transaction_id' => $attempt->provider_transaction_id,
        'merchant_reference' => $attempt->public_id,
        'event_type' => 'payment.succeeded',
        'status' => 'succeeded',
        'amount_minor' => $attempt->amount_minor,
        'currency' => $attempt->currency,
        'occurred_at' => now()->toIso8601String(),
    ], $overrides));

    return PaymentFixtures::postWebhook('test', $signed['body'], $signed['headers']);
}

it('accepts a valid signed webhook and commits inventory once', function (): void {
    Event::fake([PaymentSucceeded::class, InventoryCommittedForOrder::class]);
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'wh1');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'wh1-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    $first = deliverSuccessWebhook((string) $created->json('data.id'));
    $first->assertOk()->assertJsonPath('received', true);

    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->status)->toBe(OrderStatus::Confirmed)
        ->and($order?->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order?->paid_at)->not->toBeNull()
        ->and(InventoryReservation::query()->where('reference_id', $pending['order_id'])->where('status', InventoryReservationStatus::Committed)->count())->toBe(1);

    $ledgerCount = InventoryLedgerEntry::query()->count();
    $second = deliverSuccessWebhook((string) $created->json('data.id'));
    $second->assertOk();
    expect(InventoryLedgerEntry::query()->count())->toBe($ledgerCount)
        ->and(PaymentWebhook::query()->where('processing_status', PaymentWebhookProcessingStatus::Processed->value)->count())->toBe(1);

    Event::assertDispatched(PaymentSucceeded::class);
    Event::assertDispatched(InventoryCommittedForOrder::class);
});

it('rejects missing and invalid signatures', function (): void {
    test()->postJson('/api/v1/payments/webhooks/test', ['status' => 'succeeded'])
        ->assertStatus(400);

    $signed = PaymentFixtures::signedWebhook([
        'event_id' => 'evt_bad',
        'payment_id' => 'x',
        'status' => 'succeeded',
        'event_type' => 'payment.succeeded',
        'amount_minor' => 1,
        'currency' => 'GEL',
    ], secret: 'wrong-secret');

    PaymentFixtures::postWebhook('test', $signed['body'], $signed['headers'])->assertStatus(400);
});

it('rejects unknown providers and oversized payloads', function (): void {
    test()->postJson('/api/v1/payments/webhooks/not-a-bank', [])->assertStatus(404);

    $huge = str_repeat('a', (int) config('payments.webhook_max_bytes') + 10);
    PaymentFixtures::postWebhook('test', '{"x":"'.$huge.'"}', ['Content-Type' => 'application/json'])->assertStatus(413);
});

it('does not mark paid on amount or currency mismatch', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'wh2');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'wh2-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    deliverSuccessWebhook((string) $created->json('data.id'), ['amount_minor' => 1])->assertOk();
    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->payment_status)->not->toBe(PaymentStatus::Paid)
        ->and($order?->status)->toBe(OrderStatus::ManualReview);
});

it('ignores a later failure after success', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'wh3');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'wh3-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    deliverSuccessWebhook((string) $created->json('data.id'))->assertOk();
    deliverSuccessWebhook((string) $created->json('data.id'), [
        'event_id' => 'evt_fail_later',
        'status' => 'failed',
        'event_type' => 'payment.failed',
    ])->assertOk();

    expect(PaymentAttempt::query()->where('public_id', $created->json('data.id'))->first()?->status)
        ->toBe(PaymentAttemptStatus::Succeeded)
        ->and(Order::query()->where('public_id', $pending['order_id'])->first()?->payment_status)->toBe(PaymentStatus::Paid);
});

it('stores unmatched webhooks for review', function (): void {
    $signed = PaymentFixtures::signedWebhook([
        'event_id' => 'evt_orphan',
        'payment_id' => 'testpay_missing',
        'status' => 'succeeded',
        'event_type' => 'payment.succeeded',
        'amount_minor' => 100,
        'currency' => 'GEL',
    ]);
    PaymentFixtures::postWebhook('test', $signed['body'], $signed['headers'])->assertOk();

    expect(PaymentWebhook::query()->where('provider_event_id', 'evt_orphan')->first()?->processing_status)
        ->toBe(PaymentWebhookProcessingStatus::Unmatched);
});

it('sends late success after expiration to manual review without confirming', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'wh4');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'wh4-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    Order::query()->where('public_id', $pending['order_id'])->update([
        'reservation_expires_at' => now()->subMinute(),
    ]);
    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    deliverSuccessWebhook((string) $created->json('data.id'))->assertOk();
    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->status)->toBe(OrderStatus::ManualReview)
        ->and($order?->payment_status)->not->toBe(PaymentStatus::Paid)
        ->and(InventoryReservation::query()->where('reference_id', $pending['order_id'])->where('status', InventoryReservationStatus::Committed)->count())->toBe(0);
});
