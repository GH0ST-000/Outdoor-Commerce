<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Support;

final class WarehouseCodeNormalizer
{
    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }
}
