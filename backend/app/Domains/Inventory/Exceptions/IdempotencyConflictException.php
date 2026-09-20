<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

final class IdempotencyConflictException extends InventoryStateConflictException
{
    public function __construct(string $message = 'Idempotency key was already used with a different payload.')
    {
        parent::__construct($message, 'IDEMPOTENCY_CONFLICT');
    }
}
