<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class TransferWarehousesMatchException extends DomainException
{
    public function __construct(string $message = 'Source and destination warehouses must differ.')
    {
        parent::__construct($message, 'TRANSFER_WAREHOUSES_MATCH');
    }
}
