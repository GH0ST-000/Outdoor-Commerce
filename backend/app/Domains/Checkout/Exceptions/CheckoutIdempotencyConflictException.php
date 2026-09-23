<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class CheckoutIdempotencyConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'This idempotency key was already used with a different payload.',
            'CHECKOUT_IDEMPOTENCY_CONFLICT',
        );
    }
}
