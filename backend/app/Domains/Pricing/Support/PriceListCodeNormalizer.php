<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Support;

final class PriceListCodeNormalizer
{
    public static function normalize(string $code): string
    {
        return strtolower(trim($code));
    }
}
