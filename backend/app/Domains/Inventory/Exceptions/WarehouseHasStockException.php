<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class WarehouseHasStockException extends DomainException
{
    public function __construct(string $message = 'Warehouse still holds stock and cannot be removed.')
    {
        parent::__construct($message, 'WAREHOUSE_HAS_STOCK');
    }
}
