<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Testing\TestResponse;

final class OrderFixtures
{
    /**
     * @return array<string, string>
     */
    public static function headers(string $key): array
    {
        return [
            'Idempotency-Key' => $key,
            'Accept' => 'application/json',
            'X-Locale' => 'ka',
        ];
    }

    public static function guestOrderCookie(TestResponse $response): ?string
    {
        $name = (string) config('order.cookie.name', 'outdoor_guest_order');

        try {
            return $response->getCookie($name)?->getValue();
        } catch (DecryptException) {
            return $response->getCookie($name, decrypt: false)?->getValue();
        }
    }

    /**
     * @return array{
     *     cookie: string,
     *     session_id: string,
     *     quote_id: string,
     *     checkout_version: int,
     *     cart_version: int,
     *     quote: TestResponse
     * }
     */
    public static function quotedGuestCheckout(int $variantId, int $quantity = 1, string $prefix = 'ord'): array
    {
        CheckoutFixtures::seedFulfillment();
        $added = test()->withHeaders(CartFixtures::idempotencyHeader($prefix.'-add'))
            ->postJson('/api/v1/cart/items', [
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ])->assertOk();
        $cookie = (string) CartFixtures::guestCookie($added);

        $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
            ->withHeaders(CheckoutFixtures::headers($prefix.'-s'))
            ->postJson('/api/v1/checkout/sessions')
            ->assertOk();
        $id = CheckoutFixtures::sessionId($created);

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

        $quote = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
            ->withHeaders(CheckoutFixtures::headers($prefix.'-q'))
            ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
                'checkout_version' => $fulfillment->json('data.checkout_session.version'),
                'cart_version' => $added->json('data.version'),
            ])->assertOk();

        return [
            'cookie' => $cookie,
            'session_id' => $id,
            'quote_id' => (string) $quote->json('data.quote.id'),
            'checkout_version' => (int) $quote->json('data.checkout_session.version'),
            'cart_version' => (int) $added->json('data.version'),
            'quote' => $quote,
        ];
    }

    /**
     * @return array{
     *     cookie: string,
     *     session_id: string,
     *     quote_id: string,
     *     checkout_version: int,
     *     cart_version: int,
     *     quote: TestResponse
     * }
     */
    public static function quotedGuestDeliveryCheckout(int $variantId, int $quantity = 1, string $prefix = 'del'): array
    {
        CheckoutFixtures::seedFulfillment();
        $added = test()->withHeaders(CartFixtures::idempotencyHeader($prefix.'-add'))
            ->postJson('/api/v1/cart/items', [
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ])->assertOk();
        $cookie = (string) CartFixtures::guestCookie($added);

        $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
            ->withHeaders(CheckoutFixtures::headers($prefix.'-s'))
            ->postJson('/api/v1/checkout/sessions')
            ->assertOk();
        $id = CheckoutFixtures::sessionId($created);

        $contact = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
            ->withHeaders(CheckoutFixtures::headers($prefix.'-c'))
            ->patchJson('/api/v1/checkout/sessions/'.$id.'/contact', CheckoutFixtures::contact([
                'checkout_version' => $created->json('data.checkout_session.version'),
            ]))->assertOk();

        $address = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
            ->withHeaders(CheckoutFixtures::headers($prefix.'-a'))
            ->patchJson('/api/v1/checkout/sessions/'.$id.'/address', CheckoutFixtures::tbilisiAddress([
                'checkout_version' => $contact->json('data.checkout_session.version'),
            ]))->assertOk();

        $fulfillment = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
            ->withHeaders(CheckoutFixtures::headers($prefix.'-f'))
            ->patchJson('/api/v1/checkout/sessions/'.$id.'/fulfillment', [
                'method_code' => 'local_delivery',
                'checkout_version' => $address->json('data.checkout_session.version'),
            ])->assertOk();

        $quote = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $cookie)
            ->withHeaders(CheckoutFixtures::headers($prefix.'-q'))
            ->postJson('/api/v1/checkout/sessions/'.$id.'/quote', [
                'checkout_version' => $fulfillment->json('data.checkout_session.version'),
                'cart_version' => $added->json('data.version'),
            ])->assertOk();

        return [
            'cookie' => $cookie,
            'session_id' => $id,
            'quote_id' => (string) $quote->json('data.quote.id'),
            'checkout_version' => (int) $quote->json('data.checkout_session.version'),
            'cart_version' => (int) $added->json('data.version'),
            'quote' => $quote,
        ];
    }
}
