<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

final class StaleInventoryVersionException extends InventoryStateConflictException
{
    public function __construct(string $message = 'Inventory balance was updated by another process.')
    {
        parent::__construct($message, 'STALE_INVENTORY_VERSION');
    }
}
