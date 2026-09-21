<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class InsufficientStockException extends DomainException
{
    public function __construct(string $message = 'Insufficient stock for this operation.')
    {
        parent::__construct($message, 'INSUFFICIENT_STOCK');
    }
}
