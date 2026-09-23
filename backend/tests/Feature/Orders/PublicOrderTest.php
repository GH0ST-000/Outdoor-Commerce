<?php

declare(strict_types=1);

use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderCreated;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\OrderStatusHistory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Support\CartFixtures;
use Tests\Support\CheckoutFixtures;
use Tests\Support\OrderFixtures;
use Tests\Support\PublicCatalogFixtures;

it('creates an order from a valid active quote', function (): void {
    Event::fake([OrderCreated::class]);
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 5, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id);
    $quoteTotals = $quoted['quote']->json('data.quote.totals');

    $response = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('create-1'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
            'grand_total' => 1,
        ]);

    $response->assertStatus(422);

    $response = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('create-1b'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.fulfillment_status', 'unfulfilled')
        ->assertJsonPath('data.totals.grand_total_minor', $quoteTotals['grand_total_minor'])
        ->assertJsonPath('data.totals.items_subtotal_minor', $quoteTotals['items_subtotal_minor'])
        ->assertJsonMissingPath('data.id_numeric')
        ->assertJsonMissingPath('data.access_token_hash');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->json('data.order_number'))->toMatch('/^ORD-\d{8}-[0-9A-HJKMNP-TV-Z]{6}$/')
        ->and($response->json('data.id'))->not->toMatch('/^\d+$/')
        ->and($response->json('data.can_cancel'))->toBeTrue();

    $order = Order::query()->where('public_id', $response->json('data.id'))->first();
    expect($order)->not->toBeNull()
        ->and($order?->status)->toBe(OrderStatus::PendingPayment)
        ->and($order?->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order?->fulfillment_status)->toBe(FulfillmentStatus::Unfulfilled)
        ->and(OrderItem::query()->where('order_id', $order?->id)->count())->toBe(1)
        ->and(OrderStatusHistory::query()->where('order_id', $order?->id)->count())->toBe(1);

    $quote = CheckoutQuote::query()->where('public_id', $quoted['quote_id'])->first();
    $session = CheckoutSession::query()->where('public_id', $quoted['session_id'])->first();
    $cart = Cart::query()->whereKey($session?->cart_id)->first();
    expect($quote?->status)->toBe(CheckoutQuoteStatus::Consumed)
        ->and($session?->status)->toBe(CheckoutSessionStatus::Converted)
        ->and($cart?->status)->toBe(CartStatus::Converted)
        ->and($cart?->converted_order_id)->toBe($order?->id);

    $reservation = InventoryReservation::query()->where('reference_type', 'order')->where('reference_id', $order?->public_id)->first();
    expect($reservation?->status)->toBe(InventoryReservationStatus::Active)
        ->and($reservation?->quantity)->toBe(1)
        ->and(InventoryReservation::query()->where('reference_type', 'checkout_quote')->where('status', InventoryReservationStatus::Active)->count())->toBe(0);

    Event::assertDispatched(OrderCreated::class);
});

it('replays the same idempotency key without creating a second order', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'idem');

    $payload = [
        'checkout_session_id' => $quoted['session_id'],
        'quote_id' => $quoted['quote_id'],
        'checkout_version' => $quoted['checkout_version'],
    ];

    $first = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('same-key'))
        ->postJson('/api/v1/orders', $payload)
        ->assertCreated();

    $second = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('same-key'))
        ->postJson('/api/v1/orders', $payload)
        ->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Order::query()->count())->toBe(1);
});

it('conflicts when the same idempotency key is reused with a different payload', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'conf');

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('conflict-key'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertCreated();

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('conflict-key'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'] + 99,
        ])->assertStatus(409)
        ->assertJsonPath('error.code', 'ORDER_IDEMPOTENCY_CONFLICT');
});

it('returns the existing order when a second key targets the same quote', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'two-keys');

    $payload = [
        'checkout_session_id' => $quoted['session_id'],
        'quote_id' => $quoted['quote_id'],
        'checkout_version' => $quoted['checkout_version'],
    ];

    $first = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('key-a'))
        ->postJson('/api/v1/orders', $payload)
        ->assertCreated();

    $second = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('key-b'))
        ->postJson('/api/v1/orders', $payload)
        ->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Order::query()->count())->toBe(1);
});

it('requires an idempotency key', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'no-key');

    $this->flushHeaders();
    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(['Accept' => 'application/json', 'X-Locale' => 'ka'])
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'ORDER_IDEMPOTENCY_REQUIRED');
});

it('rejects an expired quote', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'exp');
    CheckoutQuote::query()->where('public_id', $quoted['quote_id'])->update([
        'expires_at' => now()->subMinute(),
    ]);

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('exp-k'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'ORDER_QUOTE_EXPIRED');
});

it('rejects a superseded quote', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'sup');
    $oldId = $quoted['quote_id'];

    $refresh = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(CheckoutFixtures::headers('sup-q2'))
        ->postJson('/api/v1/checkout/sessions/'.$quoted['session_id'].'/quote', [
            'checkout_version' => $quoted['checkout_version'],
            'cart_version' => $quoted['cart_version'],
        ])->assertOk();

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('sup-ord'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $oldId,
            'checkout_version' => (int) $refresh->json('data.checkout_session.version'),
        ])->assertStatus(409)
        ->assertJsonPath('error.code', 'ORDER_QUOTE_SUPERSEDED');
});

it('rejects a fingerprint mismatch', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'fp');
    CheckoutQuote::query()->where('public_id', $quoted['quote_id'])->update([
        'quote_fingerprint' => str_repeat('ab', 32),
    ]);

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('fp-k'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'ORDER_QUOTE_INTEGRITY_FAILED');
});

it('returns a version conflict for a stale checkout version', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'ver');

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('ver-k'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => 0,
        ])->assertStatus(409)
        ->assertJsonPath('error.code', 'ORDER_VERSION_CONFLICT');
});

it('rejects missing reservations', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'res');
    InventoryReservation::query()->delete();

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('res-k'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'ORDER_RESERVATION_MISSING');

    expect(Order::query()->count())->toBe(0)
        ->and(CheckoutQuote::query()->where('public_id', $quoted['quote_id'])->value('status'))->toBe(CheckoutQuoteStatus::Active);
});

it('lets a guest read and cancel with the order cookie', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'gacc');

    $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('gacc-c'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertCreated();

    $orderId = (string) $created->json('data.id');
    $orderCookie = OrderFixtures::guestOrderCookie($created);
    expect($orderCookie)->not->toBeNull()
        ->and($created->json('data'))->not->toHaveKey('access_token');

    test()->getJson('/api/v1/orders/'.$orderId)
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'ORDER_NOT_FOUND');

    test()->withUnencryptedCookie((string) config('order.cookie.name'), (string) $orderCookie)
        ->getJson('/api/v1/orders/'.$orderId)
        ->assertOk()
        ->assertJsonPath('data.order_number', $created->json('data.order_number'));

    $number = (string) $created->json('data.order_number');
    test()->withUnencryptedCookie((string) config('order.cookie.name'), (string) $orderCookie)
        ->getJson('/api/v1/orders/'.$number)
        ->assertStatus(404);

    test()->withUnencryptedCookie((string) config('order.cookie.name'), (string) $orderCookie)
        ->withHeaders(OrderFixtures::headers('gacc-x'))
        ->postJson('/api/v1/orders/'.$orderId.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect(InventoryReservation::query()->where('reference_id', $orderId)->where('status', InventoryReservationStatus::Active)->count())->toBe(0);

    test()->withUnencryptedCookie((string) config('order.cookie.name'), (string) $orderCookie)
        ->withHeaders(OrderFixtures::headers('gacc-x2'))
        ->postJson('/api/v1/orders/'.$orderId.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('does not let another user access or cancel an order', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);

    $this->actingAs($owner);
    $added = test()->actingAs($owner)->withHeaders(CartFixtures::idempotencyHeader('au-add'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => 1])
        ->assertOk();

    CheckoutFixtures::seedFulfillment();
    $created = test()->actingAs($owner)->withHeaders(CheckoutFixtures::headers('au-s'))
        ->postJson('/api/v1/checkout/sessions')->assertOk();
    $id = CheckoutFixtures::sessionId($created);
    $contact = test()->actingAs($owner)->withHeaders(CheckoutFixtures::headers('au-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();
    $fulfillment = test()->actingAs($owner)->withHeaders(CheckoutFixtures::headers('au-f'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'store_pickup',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ])->assertOk();
    $quote = test()->actingAs($owner)->withHeaders(CheckoutFixtures::headers('au-q'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
        ])->assertOk();
    $order = test()->actingAs($owner)->withHeaders(OrderFixtures::headers('au-o'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $id,
            'quote_id' => $quote->json('data.quote.id'),
            'checkout_version' => $quote->json('data.checkout_session.version'),
        ])->assertCreated();
    $orderId = (string) $order->json('data.id');

    test()->actingAs($other)->getJson('/api/v1/orders/'.$orderId)
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'ORDER_NOT_FOUND');
    test()->actingAs($other)->withHeaders(OrderFixtures::headers('au-x'))
        ->postJson('/api/v1/orders/'.$orderId.'/cancel')
        ->assertStatus(404);
});

it('keeps order item snapshots after catalog changes', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'snap');
    $originalName = (string) $quoted['quote']->json('data.quote.items.0.name');
    $originalSku = (string) $quoted['quote']->json('data.quote.items.0.sku');
    $originalTotal = (int) $quoted['quote']->json('data.quote.totals.grand_total_minor');

    $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('snap-c'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertCreated();

    $fixture['product']->update(['sku' => 'CHANGED']);
    $fixture['variant']->update(['sku' => 'CHANGED-SKU']);

    $orderCookie = OrderFixtures::guestOrderCookie($created);
    $fresh = test()->withUnencryptedCookie((string) config('order.cookie.name'), (string) $orderCookie)
        ->getJson('/api/v1/orders/'.$created->json('data.id'))
        ->assertOk();

    expect($fresh->json('data.items.0.name'))->toBe($originalName)
        ->and($fresh->json('data.items.0.sku'))->toBe($originalSku)
        ->and($fresh->json('data.totals.grand_total_minor'))->toBe($originalTotal);
});

it('creates a new cart after conversion', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 5, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'newc');

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('newc-o'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertCreated();

    $again = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(CartFixtures::idempotencyHeader('newc-add2'))
        ->postJson('/api/v1/cart/items', [
            'variant_id' => $fixture['variant']->id,
            'quantity' => 1,
        ])->assertOk();

    expect($again->json('data.status'))->toBe('active')
        ->and(Cart::query()->where('status', CartStatus::Converted)->count())->toBe(1)
        ->and(Cart::query()->where('status', CartStatus::Active)->count())->toBe(1);
});

it('does not log guest tokens or emails during order creation', function (): void {
    Log::spy();
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2, 'price' => 10000]);
    $quoted = OrderFixtures::quotedGuestCheckout($fixture['variant']->id, 1, 'log');

    $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
        ->withHeaders(OrderFixtures::headers('log-c'))
        ->postJson('/api/v1/orders', [
            'checkout_session_id' => $quoted['session_id'],
            'quote_id' => $quoted['quote_id'],
            'checkout_version' => $quoted['checkout_version'],
        ])->assertCreated();

    $token = OrderFixtures::guestOrderCookie($created);
    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($token): bool {
        $encoded = json_encode($context);

        return ! str_contains($encoded ?: '', (string) $token)
            && ! str_contains($encoded ?: '', 'nino@example.com')
            && ! str_contains($encoded ?: '', '555123456');
    })->atLeast()->once();
});
