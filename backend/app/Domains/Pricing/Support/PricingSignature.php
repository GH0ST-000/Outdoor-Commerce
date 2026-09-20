<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Support;

final class PricingSignature
{
    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function hash(array $inputs): string
    {
        $encoded = json_encode($inputs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $encoded);
    }
}
