<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Testing\TestResponse;

final class CartFixtures
{
    public static function idempotencyHeader(string $key): array
    {
        return [
            'Idempotency-Key' => $key,
            'Accept' => 'application/json',
            'X-Locale' => 'ka',
        ];
    }

    public static function guestCookie(TestResponse $response): ?string
    {
        $name = (string) config('cart.cookie.name', 'outdoor_guest_cart');

        try {
            return $response->getCookie($name)?->getValue();
        } catch (DecryptException) {
            return $response->getCookie($name, decrypt: false)?->getValue();
        }
    }
}
