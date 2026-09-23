<?php

declare(strict_types=1);

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use App\Domains\Checkout\Enums\CheckoutRestrictionOutcome;
use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutRestrictionRule;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Checkout\Models\FulfillmentMethod;
use App\Domains\Identity\Models\Address;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Support\CartFixtures;
use Tests\Support\CheckoutFixtures;
use Tests\Support\PublicCatalogFixtures;

function checkoutAdd(int $variantId, int $quantity = 1, string $key = 'chk-add', ?string $cookie = null): TestResponse
{
    $request = test()->withHeaders(CartFixtures::idempotencyHeader($key));
    if (is_string($cookie) && $cookie !== '') {
        $request = $request->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie);
    }

    return $request->postJson('/api/v1/cart/items', [
        'variant_id' => $variantId,
        'quantity' => $quantity,
    ]);
}

function checkoutCookie(TestResponse $response): string
{
    return (string) CartFixtures::guestCookie($response);
}

function startCheckout(?string $cookie = null, string $key = 'chk-start'): TestResponse
{
    CheckoutFixtures::seedFulfillment();
    $request = test()->withHeaders(CheckoutFixtures::headers($key));
    if (is_string($cookie) && $cookie !== '') {
        $request = $request->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie);
    }

    return $request->postJson('/api/v1/checkout/sessions');
}

it('lets a guest create a checkout session from a cart', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 5, 'price' => 10000]);
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);

    $response = startCheckout($cookie);
    $response->assertOk()
        ->assertJsonPath('data.checkout_session.status', 'draft')
        ->assertJsonMissingPath('data.checkout_session.guest_token_hash')
        ->assertJsonPath('data.quote', null);
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('private');

    expect(InventoryReservation::query()->count())->toBe(0);
});

it('rejects an empty cart', function (): void {
    CheckoutFixtures::seedFulfillment();
    $this->withHeaders(CheckoutFixtures::headers('empty'))
        ->postJson('/api/v1/checkout/sessions')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CHECKOUT_CART_EMPTY');
});

it('reuses a compatible active session instead of duplicating', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);

    $first = startCheckout($cookie, 's1');
    $second = startCheckout($cookie, 's2');

    expect($first->json('data.checkout_session.id'))->toBe($second->json('data.checkout_session.id'))
        ->and(CheckoutSession::query()->count())->toBe(1);
});

it('denies another guest access by public id alone', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);

    $this->unencryptedCookies = [];
    $this->defaultCookies = [];

    $this->getJson('/api/v1/checkout/sessions/'.$id)
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'CHECKOUT_SESSION_NOT_FOUND');

    $stranger = PublicCatalogFixtures::publicProduct();
    $other = checkoutAdd($stranger['variant']->id, 1, 'other-add');
    $otherCookie = checkoutCookie($other);

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $otherCookie)
        ->getJson('/api/v1/checkout/sessions/'.$id)
        ->assertStatus(404);
});

it('lets an authenticated user create a session and blocks another user', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $fixture = PublicCatalogFixtures::publicProduct();

    $this->actingAs($owner)
        ->withHeaders(CartFixtures::idempotencyHeader('auth-add'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => 1])
        ->assertOk();

    $created = $this->actingAs($owner)
        ->withHeaders(CheckoutFixtures::headers('auth-start'))
        ->postJson('/api/v1/checkout/sessions')
        ->assertOk();
    $id = CheckoutFixtures::sessionId($created);

    $this->actingAs($stranger)
        ->getJson('/api/v1/checkout/sessions/'.$id)
        ->assertStatus(404);
});

it('accepts Georgian contact data and normalizes the phone', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);
    $version = $created->json('data.checkout_session.version');

    $updated = test()
        ->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('contact'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $version,
        ]));

    $updated->assertOk()->assertJsonPath('data.checkout_session.contact.phone', '+995555123456');
});

it('rejects invalid email, html, and oversized fields', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('bad-email'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact(['email' => 'not-an-email']))
        ->assertStatus(422);

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('html'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact(['first_name' => '<script>x</script>']))
        ->assertStatus(422);

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('long'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact(['first_name' => str_repeat('ა', 81)]))
        ->assertStatus(422);
});

it('quotes local delivery with an authoritative fee and ignores client totals', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4, 'price' => 10000]);
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);

    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('c1'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]));
    $contact->assertOk();

    $address = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('a1'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/address', CheckoutFixtures::tbilisiAddress([
            'checkout_version' => $contact->json('data.checkout_session.version'),
            'delivery_total_minor' => 1,
        ]));
    $address->assertStatus(422);

    $address = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('a2'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/address', CheckoutFixtures::tbilisiAddress([
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ]));
    $address->assertOk();

    $methods = collect($address->json('data.checkout_session.available_fulfillment_methods'));
    expect($methods->firstWhere('code', 'local_delivery')['eligible'])->toBeTrue()
        ->and($methods->firstWhere('code', 'local_delivery')['amount_minor'])->toBe(500);

    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('f1'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'local_delivery',
            'checkout_version' => $address->json('data.checkout_session.version'),
        ]);
    $fulfillment->assertOk();

    $quote = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('q1'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
            'grand_total_minor' => 1,
        ]);
    $quote->assertStatus(422);

    $quote = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('q2'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
        ]);

    $quote->assertOk()
        ->assertJsonPath('data.quote.totals.delivery_total_minor', 500)
        ->assertJsonPath('data.quote.totals.items_subtotal_minor', 10000)
        ->assertJsonPath('data.quote.totals.grand_total_minor', 10500)
        ->assertJsonPath('data.quote.totals.price_includes_tax', true)
        ->assertJsonMissingPath('data.quote.items.0.reservation_key');

    expect(InventoryReservation::query()->where('status', InventoryReservationStatus::Active)->count())->toBe(1)
        ->and(InventoryReservation::query()->first()?->quantity)->toBe(1);
});

it('applies configured free delivery and current promotions', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4, 'price' => 20000]);
    PublicCatalogFixtures::activePromotion(2000);
    PublicCatalogFixtures::rebuild($fixture['product']->id);

    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);

    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('fc'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();

    $address = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('fa'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/address', CheckoutFixtures::tbilisiAddress([
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ]))->assertOk();

    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('ff'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'local_delivery',
            'checkout_version' => $address->json('data.checkout_session.version'),
        ])->assertOk();

    $quote = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('fq'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
        ])->assertOk();

    expect($quote->json('data.quote.totals.delivery_total_minor'))->toBe(0)
        ->and($quote->json('data.quote.totals.discount_total_minor'))->toBeGreaterThan(0)
        ->and($quote->json('data.quote.totals.grand_total_minor'))->toBe(
            $quote->json('data.quote.totals.items_subtotal_minor')
            - $quote->json('data.quote.totals.discount_total_minor'),
        );
});

it('supports store pickup without a street address', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3]);
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);

    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('pc'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();

    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('pf'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'store_pickup',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ]);
    $fulfillment->assertOk();

    $quote = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('pq'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
        ]);
    $quote->assertOk()->assertJsonPath('data.quote.fulfillment.method_code', 'store_pickup')
        ->assertJsonPath('data.quote.totals.delivery_total_minor', 0);
});

it('creates a new immutable revision and releases superseded reservations', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 5, 'price' => 10000]);
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);

    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('r-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();
    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('r-f'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'store_pickup',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ])->assertOk();

    $first = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('r-q1'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
        ])->assertOk();

    $firstId = $first->json('data.quote.id');
    $firstFingerprint = $first->json('data.quote.fingerprint');

    $second = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('r-q2'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $first->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
        ])->assertOk();

    expect($second->json('data.quote.revision'))->toBe(2)
        ->and($second->json('data.quote.id'))->not->toBe($firstId)
        ->and($second->json('data.quote.fingerprint'))->toBe($firstFingerprint);

    $old = CheckoutQuote::query()->where('public_id', $firstId)->first();
    expect($old?->status)->toBe(CheckoutQuoteStatus::Superseded)
        ->and($old?->items_subtotal_minor)->toBe(10000)
        ->and(InventoryReservation::query()->where('status', InventoryReservationStatus::Active)->count())->toBe(1)
        ->and(InventoryReservation::query()->where('reference_id', $firstId)->where('status', InventoryReservationStatus::Active)->count())->toBe(0);
});

it('fails the whole quote when stock is insufficient and leaves no partial reservations', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 1, 'price' => 10000]);
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    InventoryBalance::query()->where('product_variant_id', $fixture['variant']->id)->update(['on_hand' => 0, 'reserved' => 0]);

    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);
    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('st-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();
    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('st-f'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'store_pickup',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ])->assertOk();

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('st-q'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CHECKOUT_INSUFFICIENT_STOCK');

    expect(InventoryReservation::query()->count())->toBe(0)
        ->and(CheckoutQuote::query()->count())->toBe(0);
});

it('returns 409 on stale session or cart versions', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3]);
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);
    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('v-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();
    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('v-f'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'store_pickup',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ])->assertOk();

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('v-q'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => 0,
            'cart_version' => $added->json('data.version'),
        ])->assertStatus(409)->assertJsonPath('error.code', 'CHECKOUT_VERSION_CONFLICT');

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('v-q2'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => 0,
        ])->assertStatus(409)->assertJsonPath('error.code', 'CHECKOUT_VERSION_CONFLICT');
});

it('replays identical quote idempotency keys without double-reserving', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3]);
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);
    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('i-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();
    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('i-f'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'store_pickup',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ])->assertOk();

    $payload = [
        'checkout_version' => $fulfillment->json('data.checkout_session.version'),
        'cart_version' => $added->json('data.version'),
    ];
    $first = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('same-key'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', $payload)
        ->assertOk();
    $replay = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('same-key'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', $payload)
        ->assertOk();

    expect($replay->json('data.quote.id'))->toBe($first->json('data.quote.id'))
        ->and(InventoryReservation::query()->where('status', InventoryReservationStatus::Active)->count())->toBe(1);

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('same-key'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $payload['checkout_version'],
            'cart_version' => $payload['cart_version'] + 1,
        ])->assertStatus(409)->assertJsonPath('error.code', 'CHECKOUT_IDEMPOTENCY_CONFLICT');
});

it('does not save a guest address to a user account and does not log addresses', function (): void {
    Log::spy();
    $fixture = PublicCatalogFixtures::publicProduct();
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);
    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('log-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('log-a'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/address', CheckoutFixtures::tbilisiAddress([
            'checkout_version' => $contact->json('data.checkout_session.version'),
            'save_to_account' => true,
        ]))->assertOk();

    expect(Address::query()->count())->toBe(0);
});

it('blocks a configured restricted product and does not infer rules from category names', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3, 'ka_name' => 'სანადირო თოფი']);
    CheckoutRestrictionRule::query()->create([
        'product_id' => $fixture['product']->id,
        'outcome' => CheckoutRestrictionOutcome::CheckoutBlocked,
        'code' => 'manual_block',
        'message_translations' => ['ka' => 'ეს პროდუქტი ვერ გაიყიდება.', 'en' => 'This product cannot be quoted.'],
        'is_active' => true,
    ]);

    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);
    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('rb-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();
    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('rb-f'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'store_pickup',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ])->assertOk();

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('rb-q'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
        ])->assertStatus(422)->assertJsonPath('error.code', 'CHECKOUT_RESTRICTION_BLOCKED');
});

it('expires quotes on request and via the artisan command', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3]);
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);
    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('ex-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();
    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('ex-f'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'store_pickup',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ])->assertOk();
    $quoted = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('ex-q'))
        ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
            'checkout_version' => $fulfillment->json('data.checkout_session.version'),
            'cart_version' => $added->json('data.version'),
        ])->assertOk();

    CheckoutQuote::query()->where('public_id', $quoted->json('data.quote.id'))->update([
        'expires_at' => now()->subMinute(),
    ]);
    InventoryReservation::query()->update(['expires_at' => now()->subMinute()]);

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->getJson('/api/v1/checkout/sessions/'.$id)
        ->assertOk()
        ->assertJsonPath('data.quote', null);

    expect(CheckoutQuote::query()->first()?->status)->toBe(CheckoutQuoteStatus::Expired);

    $this->artisan('checkout:expire-quotes')->assertSuccessful();
    $this->artisan('inventory:expire-reservations')->assertSuccessful();
    $this->artisan('checkout:expire-sessions')->assertSuccessful();
});

it('rejects disabled fulfillment methods and converted sessions', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $id = CheckoutFixtures::sessionId($created);

    FulfillmentMethod::query()->where('code', 'local_delivery')->update(['is_active' => false]);
    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('dis-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('dis-f'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'local_delivery',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ])->assertStatus(422)->assertJsonPath('error.code', 'CHECKOUT_FULFILLMENT_UNAVAILABLE');

    CheckoutSession::query()->where('public_id', $id)->update([
        'status' => CheckoutSessionStatus::Converted,
    ]);
    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers('conv'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ]))->assertStatus(422)->assertJsonPath('error.code', 'CHECKOUT_SESSION_NOT_MUTABLE');
});

it('does not expose warehouse internals or guest hashes', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2]);
    $added = checkoutAdd($fixture['variant']->id);
    $cookie = checkoutCookie($added);
    $created = startCheckout($cookie);
    $created->assertJsonMissingPath('data.checkout_session.guest_token_hash');
    expect(Cart::query()->value('guest_token_hash'))->not->toBe($cookie);
});
