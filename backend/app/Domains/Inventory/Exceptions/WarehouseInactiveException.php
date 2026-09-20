<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class WarehouseInactiveException extends DomainException
{
    public function __construct(string $message = 'Warehouse is not active for stock operations.')
    {
        parent::__construct($message, 'WAREHOUSE_INACTIVE');
    }
}
