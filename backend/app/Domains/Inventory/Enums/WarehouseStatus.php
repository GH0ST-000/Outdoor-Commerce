<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum WarehouseStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function allowsStockOperations(): bool
    {
        return $this === self::Active;
    }
}
