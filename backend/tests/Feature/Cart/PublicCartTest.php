<?php

declare(strict_types=1);

use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Pricing\Models\PricePeriod;
use Illuminate\Testing\TestResponse;
use Tests\Support\CartFixtures;
use Tests\Support\PublicCatalogFixtures;

function addItem(int $variantId, int $quantity = 1, string $key = 'add-1', array $extra = [], ?string $cookie = null): TestResponse
{
    $request = test()->withHeaders(CartFixtures::idempotencyHeader($key));
    if (is_string($cookie) && $cookie !== '') {
        $request = $request->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie);
    }

    return $request->postJson('/api/v1/cart/items', array_merge([
        'variant_id' => $variantId,
        'quantity' => $quantity,
    ], $extra));
}

it('returns an empty cart without creating a database row', function (): void {
    $this->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.id', null)
        ->assertJsonPath('data.item_count', 0)
        ->assertJsonPath('data.totals.cart_total_minor', 0);

    expect(Cart::query()->count())->toBe(0);
});

it('creates a guest cart and hashes the credential', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 5, 'price' => 12999]);

    $response = addItem($fixture['variant']->id);
    $response->assertOk()->assertJsonPath('data.item_count', 1);

    $raw = CartFixtures::guestCookie($response);
    expect($raw)->toBeString()->not->toBeEmpty();

    $cart = Cart::query()->first();
    expect($cart)->not->toBeNull()
        ->and($cart?->guest_token_hash)->not->toBe($raw)
        ->and($cart?->guest_token_hash)->toHaveLength(64)
        ->and(Cart::query()->where('guest_token_hash', $raw)->exists())->toBeFalse();
});

it('rejects access by public id alone', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    addItem($fixture['variant']->id)->assertOk();
    $publicId = Cart::query()->value('public_id');

    $this->getJson('/api/v1/cart')->assertJsonPath('data.id', null);
    expect($publicId)->toBeString();
});

it('lets the guest reread their cart via the cookie', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    $created = addItem($fixture['variant']->id);
    $cookie = CartFixtures::guestCookie($created);

    $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.item_count', 1)
        ->assertJsonPath('data.items.0.variant.id', $fixture['variant']->id);
});

it('increases quantity when the same variant is added again', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 8]);
    $first = addItem($fixture['variant']->id, 1, 'inc-1');
    $cookie = CartFixtures::guestCookie($first);

    addItem($fixture['variant']->id, 2, 'inc-2', ['cart_version' => $first->json('data.version')], $cookie)
        ->assertOk()
        ->assertJsonPath('data.item_count', 3)
        ->assertJsonPath('data.unique_item_count', 1);
});

it('rejects draft products, archived products, and disabled variants', function (): void {
    $draft = PublicCatalogFixtures::publicProduct(['status' => ProductStatus::Draft]);
    addItem($draft['variant']->id, 1, 'draft')->assertStatus(422)->assertJsonPath('error.code', 'CART_PRODUCT_UNAVAILABLE');

    $archived = PublicCatalogFixtures::publicProduct(['status' => ProductStatus::Archived]);
    addItem($archived['variant']->id, 1, 'arch')->assertStatus(422)->assertJsonPath('error.code', 'CART_PRODUCT_UNAVAILABLE');

    $disabled = PublicCatalogFixtures::publicProduct();
    $disabled['variant']->update(['status' => ProductVariantStatus::Archived]);
    PublicCatalogFixtures::rebuild($disabled['product']->id);
    addItem($disabled['variant']->id, 1, 'dis')->assertStatus(422)->assertJsonPath('error.code', 'CART_VARIANT_UNAVAILABLE');
});

it('rejects invalid quantities and client-provided prices', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();

    addItem($fixture['variant']->id, 0, 'zero')->assertStatus(422);
    $this->withHeaders(CartFixtures::idempotencyHeader('neg'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => -1])
        ->assertStatus(422);
    $this->withHeaders(CartFixtures::idempotencyHeader('dec'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => 1.5])
        ->assertStatus(422);
    $this->withHeaders(CartFixtures::idempotencyHeader('price'))
        ->postJson('/api/v1/cart/items', [
            'variant_id' => $fixture['variant']->id,
            'quantity' => 1,
            'unit_price_minor' => 1,
        ])->assertStatus(422);
});

it('rejects quantity above stock and does not reserve inventory', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 2]);

    addItem($fixture['variant']->id, 3, 'over')->assertStatus(422)->assertJsonPath('error.code', 'CART_INSUFFICIENT_STOCK');
    expect(InventoryReservation::query()->count())->toBe(0);

    addItem($fixture['variant']->id, 2, 'ok')->assertOk();
    expect(InventoryReservation::query()->count())->toBe(0);
});

it('applies an active promotion to the canonical cart', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['price' => 10000]);
    PublicCatalogFixtures::activePromotion(1000);
    PublicCatalogFixtures::rebuild($fixture['product']->id);

    $response = addItem($fixture['variant']->id);
    $response->assertOk();
    expect($response->json('data.items.0.pricing.line_discount_minor'))->toBeGreaterThan(0)
        ->and($response->json('data.totals.cart_total_minor'))->toBeLessThan(10000);
});

it('updates, decrements, removes, and clears items', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 8]);
    $created = addItem($fixture['variant']->id, 2, 'u1');
    $cookie = CartFixtures::guestCookie($created);
    $itemId = $created->json('data.items.0.id');
    $version = $created->json('data.version');

    $updated = $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders(CartFixtures::idempotencyHeader('u2'))
        ->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 1, 'cart_version' => $version]);
    $updated->assertOk()->assertJsonPath('data.item_count', 1);

    $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders(CartFixtures::idempotencyHeader('u3'))
        ->deleteJson('/api/v1/cart/items/'.$itemId, ['cart_version' => $updated->json('data.version')])
        ->assertOk()
        ->assertJsonPath('data.item_count', 0);

    addItem($fixture['variant']->id, 1, 'u4', [], $cookie)->assertOk();
    $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders(CartFixtures::idempotencyHeader('u5'))
        ->deleteJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.item_count', 0);
});

it('makes repeat deletion safe', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    $created = addItem($fixture['variant']->id, 1, 'd1');
    $cookie = CartFixtures::guestCookie($created);
    $itemId = $created->json('data.items.0.id');

    $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders(CartFixtures::idempotencyHeader('d2'))
        ->deleteJson('/api/v1/cart/items/'.$itemId)
        ->assertOk();

    $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders(CartFixtures::idempotencyHeader('d3'))
        ->deleteJson('/api/v1/cart/items/'.$itemId)
        ->assertOk()
        ->assertJsonPath('data.item_count', 0);
});

it('returns 409 for a stale cart version', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 5]);
    $created = addItem($fixture['variant']->id, 1, 'v1');
    $cookie = CartFixtures::guestCookie($created);
    $itemId = $created->json('data.items.0.id');

    $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders(CartFixtures::idempotencyHeader('v2'))
        ->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 2, 'cart_version' => 0])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CART_VERSION_CONFLICT')
        ->assertJsonPath('error.details.cart.id', $created->json('data.id'));
});

it('replays an identical idempotent add and conflicts on a different payload', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 8]);
    $headers = CartFixtures::idempotencyHeader('same-key');

    $first = $this->withHeaders($headers)->postJson('/api/v1/cart/items', [
        'variant_id' => $fixture['variant']->id,
        'quantity' => 1,
    ]);
    $first->assertOk();
    $cookie = CartFixtures::guestCookie($first);

    $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders($headers)
        ->postJson('/api/v1/cart/items', [
            'variant_id' => $fixture['variant']->id,
            'quantity' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.item_count', 1);

    $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders($headers)
        ->postJson('/api/v1/cart/items', [
            'variant_id' => $fixture['variant']->id,
            'quantity' => 2,
        ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CART_IDEMPOTENCY_CONFLICT');
});

it('rejects unauthenticated merge and client-supplied cart ids', function (): void {
    $this->postJson('/api/v1/cart/merge', [
        'source_cart_id' => 'guess',
        'destination_cart_id' => 'guess',
    ])->assertUnauthorized();
});

it('prevents one user from reading or mutating another user cart', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 5]);
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($owner)
        ->withHeaders(CartFixtures::idempotencyHeader('own'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => 1])
        ->assertOk();

    $itemId = CartItem::query()->value('public_id');

    $this->actingAs($stranger)->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 0);
    $this->actingAs($stranger)
        ->withHeaders(CartFixtures::idempotencyHeader('steal'))
        ->patchJson('/api/v1/cart/items/'.$itemId, ['quantity' => 2])
        ->assertNotFound();
});

it('merges a guest cart into the authenticated cart without duplicating on repeat', function (): void {
    $a = PublicCatalogFixtures::publicProduct(['stock' => 8, 'sku' => 'SKU-A']);
    $b = PublicCatalogFixtures::publicProduct(['stock' => 8, 'sku' => 'SKU-B']);
    $guest = addItem($a['variant']->id, 2, 'm1');
    $cookie = CartFixtures::guestCookie($guest);
    addItem($b['variant']->id, 1, 'm2', ['cart_version' => $guest->json('data.version')], $cookie)->assertOk();

    $user = User::factory()->create();
    $this->actingAs($user)
        ->withHeaders(CartFixtures::idempotencyHeader('own-b'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $b['variant']->id, 'quantity' => 1])
        ->assertOk();

    $merged = $this->actingAs($user)
        ->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders(CartFixtures::idempotencyHeader('merge-1'))
        ->postJson('/api/v1/cart/merge');
    $merged->assertOk();
    expect($merged->json('data.unique_item_count'))->toBe(2)
        ->and($merged->json('data.item_count'))->toBe(4);

    $guestCart = Cart::query()->where('status', CartStatus::Merged)->first();
    expect($guestCart?->merged_into_cart_id)->not->toBeNull();

    $this->actingAs($user)
        ->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->withHeaders(CartFixtures::idempotencyHeader('merge-2'))
        ->postJson('/api/v1/cart/merge')
        ->assertOk()
        ->assertJsonPath('data.item_count', 4);
});

it('expires inactive guest carts and leaves converted carts alone', function (): void {
    $old = Cart::factory()->create([
        'status' => CartStatus::Active,
        'expires_at' => now()->subDay(),
        'last_activity_at' => now()->subDays(40),
    ]);
    $fresh = Cart::factory()->create([
        'status' => CartStatus::Active,
        'expires_at' => now()->addDays(10),
    ]);
    $converted = Cart::factory()->create([
        'status' => CartStatus::Converted,
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('carts:expire')->assertSuccessful();
    $this->artisan('carts:expire')->assertSuccessful();

    expect($old->fresh()?->status)->toBe(CartStatus::Expired)
        ->and($fresh->fresh()?->status)->toBe(CartStatus::Active)
        ->and($converted->fresh()?->status)->toBe(CartStatus::Converted);
});

it('uses current price rather than price at add and reports a change', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['price' => 5000, 'stock' => 5]);
    $created = addItem($fixture['variant']->id, 1, 'price-1');
    $created->assertJsonPath('data.items.0.pricing.unit_price_minor', 5000);
    $cookie = CartFixtures::guestCookie($created);

    PricePeriod::query()
        ->whereHas('variantPrice', fn ($q) => $q->where('product_variant_id', $fixture['variant']->id))
        ->update(['amount_minor' => 8000]);

    $reread = $this->withUnencryptedCookie((string) config('cart.cookie.name'), (string) $cookie)
        ->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.pricing.unit_price_minor', 8000);

    $codes = collect($reread->json('data.items.0.issues'))->pluck('code')->all();
    expect($codes)->toContain('PRICE_CHANGED');
});

it('marks guest cookies http-only and disables public caching', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 3]);
    $response = addItem($fixture['variant']->id, 1, 'cookie-flags');
    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('private');

    $cookie = $response->getCookie((string) config('cart.cookie.name'), decrypt: false);
    expect($cookie?->isHttpOnly())->toBeTrue();
});

it('does not reuse a converted cart as the active cart', function (): void {
    $user = User::factory()->create();
    $converted = Cart::factory()->forUser($user)->create([
        'status' => CartStatus::Converted,
        'expires_at' => now()->addDay(),
    ]);
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 4]);

    $this->actingAs($user)
        ->withHeaders(CartFixtures::idempotencyHeader('converted-add'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => 1])
        ->assertOk()
        ->assertJsonPath('data.item_count', 1);

    expect($converted->fresh()?->status)->toBe(CartStatus::Converted)
        ->and(Cart::query()->where('user_id', $user->id)->where('status', CartStatus::Active)->count())->toBe(1);
});

it('keeps quantity when two adds run without a stale version', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 8]);
    $first = addItem($fixture['variant']->id, 1, 'c-add-1');
    $cookie = CartFixtures::guestCookie($first);

    addItem($fixture['variant']->id, 1, 'c-add-2', [], $cookie)->assertOk();
    addItem($fixture['variant']->id, 1, 'c-add-3', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.item_count', 3)
        ->assertJsonPath('data.unique_item_count', 1);
});
