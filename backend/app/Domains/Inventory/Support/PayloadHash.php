<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Support;

final class PayloadHash
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
