<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\Payments\Models\PaymentAttempt;
use Tests\Support\PaymentFixtures;
use Tests\Support\PublicCatalogFixtures;

it('blocks another user from creating or reading a payment attempt', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'az1');
    $user = User::factory()->create();

    $created = test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->withHeaders(['Idempotency-Key' => 'owner', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertCreated();

    test()->actingAs($user)
        ->withHeaders(['Idempotency-Key' => 'other-user', 'Accept' => 'application/json'])
        ->postJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts', [
            'payment_method_code' => 'test_hosted_redirect',
        ])->assertNotFound();

    test()->actingAs($user)
        ->withUnencryptedCookie((string) config('order.cookie.name'), 'not-owner')
        ->getJson('/api/v1/payment-attempts/'.$created->json('data.id'))
        ->assertNotFound();

    auth()->forgetGuards();

    test()->withUnencryptedCookie((string) config('order.cookie.name'), 'not-owner')
        ->getJson('/api/v1/payment-attempts/'.$created->json('data.id'))
        ->assertNotFound();

    test()->withUnencryptedCookie((string) config('order.cookie.name'), 'not-owner')
        ->getJson('/api/v1/orders/'.$pending['order_id'].'/payment-attempts/current')
        ->assertNotFound();
});

it('does not treat browser return query parameters as payment success', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $pending = PaymentFixtures::pendingGuestOrder($fixture['variant']->id, 'az2');

    test()->withUnencryptedCookie((string) config('order.cookie.name'), $pending['order_cookie'])
        ->getJson('/api/v1/orders/'.$pending['order_id'].'?success=true&payment_status=paid')
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.status', 'pending_payment');

    expect(PaymentAttempt::query()->count())->toBe(0);
});
