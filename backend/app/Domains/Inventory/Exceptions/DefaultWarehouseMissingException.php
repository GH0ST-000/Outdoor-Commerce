<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class DefaultWarehouseMissingException extends DomainException
{
    public function __construct(string $message = 'No active default warehouse is configured.')
    {
        parent::__construct($message, 'DEFAULT_WAREHOUSE_MISSING');
    }
}
