<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

/**
 * Product lifecycle. Active means Day 7 content readiness only —
 * not purchasable (no SKU/price/inventory/media yet).
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
