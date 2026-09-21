<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Enums;

enum PublicAvailabilityStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
    case Unavailable = 'unavailable';
}
