<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class InsufficientUnreservedStockException extends DomainException
{
    public function __construct(string $message = 'Insufficient unreserved stock for this operation.')
    {
        parent::__construct($message, 'INSUFFICIENT_UNRESERVED_STOCK');
    }
}
