<?php

declare(strict_types=1);

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use Tests\Support\CartFixtures;
use Tests\Support\CheckoutFixtures;
use Tests\Support\PublicCatalogFixtures;

it('prevents concurrent quotes from overselling the last unit', function (): void {
    if ((string) config('database.default') === 'sqlite') {
        test()->markTestSkipped('SQLite cannot express the MySQL row-lock oversell race.');
    }

    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 1, 'price' => 10000]);
    CheckoutFixtures::seedFulfillment();

    $firstAdd = test()->withHeaders(CartFixtures::idempotencyHeader('c1-add'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => 1])
        ->assertOk();
    $cookieA = (string) CartFixtures::guestCookie($firstAdd);

    $secondAdd = test()->withHeaders(CartFixtures::idempotencyHeader('c2-add'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => 1])
        ->assertOk();
    $cookieB = (string) CartFixtures::guestCookie($secondAdd);

    $sessionA = readyQuotedSession($cookieA, $firstAdd->json('data.version'), 'a');
    $sessionB = readyQuotedSession($cookieB, $secondAdd->json('data.version'), 'b');

    $ok = 0;
    $failed = 0;

    $quote = static function (string $cookie, array $session, string $key) use (&$ok, &$failed): void {
        $response = test()
            ->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
            ->withHeaders(CheckoutFixtures::headers($key))
            ->postJson('/api/v1/checkout/sessions/'.$session['id'].'/quote', [
                'checkout_version' => $session['version'],
                'cart_version' => $session['cart_version'],
            ]);
        if ($response->status() === 200) {
            $ok++;
        } else {
            $failed++;
        }
    };

    $quote($cookieA, $sessionA, 'race-a');
    $quote($cookieB, $sessionB, 'race-b');

    expect($ok)->toBe(1)
        ->and($failed)->toBe(1)
        ->and((int) InventoryReservation::query()->where('status', InventoryReservationStatus::Active)->sum('quantity'))->toBe(1);
});

it('does not let a second checkout reserve the last remaining unit', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 1, 'price' => 10000]);
    CheckoutFixtures::seedFulfillment();

    $firstAdd = test()->withHeaders(CartFixtures::idempotencyHeader('seq-a-add'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => 1])
        ->assertOk();
    $cookieA = (string) CartFixtures::guestCookie($firstAdd);

    $secondAdd = test()->withHeaders(CartFixtures::idempotencyHeader('seq-b-add'))
        ->postJson('/api/v1/cart/items', ['variant_id' => $fixture['variant']->id, 'quantity' => 1])
        ->assertOk();
    $cookieB = (string) CartFixtures::guestCookie($secondAdd);

    $sessionA = readyQuotedSession($cookieA, (int) $firstAdd->json('data.version'), 'seq-a');
    $sessionB = readyQuotedSession($cookieB, (int) $secondAdd->json('data.version'), 'seq-b');

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookieA)
        ->withHeaders(CheckoutFixtures::headers('seq-a-q'))
        ->postJson('/api/v1/checkout/sessions/'.$sessionA['id'].'/quote', [
            'checkout_version' => $sessionA['version'],
            'cart_version' => $sessionA['cart_version'],
        ])->assertOk();

    test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookieB)
        ->withHeaders(CheckoutFixtures::headers('seq-b-q'))
        ->postJson('/api/v1/checkout/sessions/'.$sessionB['id'].'/quote', [
            'checkout_version' => $sessionB['version'],
            'cart_version' => $sessionB['cart_version'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CHECKOUT_INSUFFICIENT_STOCK');

    expect((int) InventoryReservation::query()->where('status', InventoryReservationStatus::Active)->sum('quantity'))->toBe(1);
});

/**
 * @return array{id: string, version: int, cart_version: int}
 */
function readyQuotedSession(string $cookie, int $cartVersion, string $prefix): array
{
    $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers($prefix.'-s'))
        ->postJson('/api/v1/checkout/sessions')
        ->assertOk();
    $id = (string) $created->json('data.checkout_session.id');
    $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers($prefix.'-c'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
            'checkout_version' => $created->json('data.checkout_session.version'),
        ]))->assertOk();
    $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
        ->withHeaders(CheckoutFixtures::headers($prefix.'-f'))
        ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
            'method_code' => 'store_pickup',
            'checkout_version' => $contact->json('data.checkout_session.version'),
        ])->assertOk();

    return [
        'id' => $id,
        'version' => (int) $fulfillment->json('data.checkout_session.version'),
        'cart_version' => $cartVersion,
    ];
}
