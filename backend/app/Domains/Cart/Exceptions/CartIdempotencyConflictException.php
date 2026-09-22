<?php

declare(strict_types=1);

namespace App\Domains\Cart\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class CartIdempotencyConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'This idempotency key was already used with a different payload.',
            'CART_IDEMPOTENCY_CONFLICT',
        );
    }
}
