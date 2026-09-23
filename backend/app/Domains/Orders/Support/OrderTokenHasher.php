<?php

declare(strict_types=1);

namespace App\Domains\Orders\Support;

final class OrderTokenHasher
{
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $rawToken): string
    {
        return hash_hmac('sha256', $rawToken, (string) config('app.key'));
    }
}
