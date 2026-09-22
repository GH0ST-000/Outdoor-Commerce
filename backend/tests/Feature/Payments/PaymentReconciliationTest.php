<?php

declare(strict_types=1);

use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Models\PaymentAttempt;
use Tests\Support\PaymentFixtures;
use Tests\Support\PublicCatalogFixtures;

it('reconciles a pending attempt to success and is idempotent', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'rc1');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'rc1-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    $attempt = PaymentAttempt::query()->where('public_id', $created->json('data.id'))->firstOrFail();
    $attempt->updated_at = now()->subMinutes(5);
    $attempt->save();
    config()->set('payments.test.scenario', 'processing_then_success');

    $this->artisan('payments:reconcile', ['--attempt' => $attempt->public_id])
        ->expectsOutputToContain('Processed 1')
        ->assertSuccessful();

    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->status)->toBe(OrderStatus::Confirmed)
        ->and($order?->payment_status)->toBe(PaymentStatus::Paid);

    $this->artisan('payments:reconcile', ['--attempt' => $attempt->public_id])->assertSuccessful();
    expect(PaymentAttempt::query()->where('public_id', $attempt->public_id)->first()?->status)
        ->toBe(PaymentAttemptStatus::Succeeded);
});

it('allows retry after a failed attempt without duplicating the order', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'rc2');
    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'rc2-c', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->postJson('/api/v1/payments/test/attempts/'.$created->json('data.id').'/simulate', [
            'outcome' => 'failure',
        ])->assertOk()
        ->assertJsonPath('data.status', 'failed');

    $order = Order::query()->where('public_id', $pending['order_id'])->first();
    expect($order?->status)->toBe(OrderStatus::PendingPayment)
        ->and(Order::query()->count())->toBe(1);

    $retry = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'rc2-retry', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    expect($retry->json('data.id'))->not->toBe($created->json('data.id'))
        ->and(PaymentAttempt::query()->count())->toBe(2);
});
