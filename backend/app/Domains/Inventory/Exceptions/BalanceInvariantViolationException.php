<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class BalanceInvariantViolationException extends DomainException
{
    public function __construct(string $message = 'Inventory balance invariants would be violated.')
    {
        parent::__construct($message, 'BALANCE_INVARIANT_VIOLATION');
    }
}
