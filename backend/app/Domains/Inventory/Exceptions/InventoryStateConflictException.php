<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Stale versions, idempotency conflicts, and invalid reservation transitions (HTTP 409).
 */
class InventoryStateConflictException extends DomainException
{
    public function __construct(
        string $message = 'Inventory state conflict.',
        string $errorCode = 'INVENTORY_STATE_CONFLICT',
    ) {
        parent::__construct($message, $errorCode, 409);
    }
}
