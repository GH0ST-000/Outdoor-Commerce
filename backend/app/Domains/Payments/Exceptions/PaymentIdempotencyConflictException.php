<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class PaymentIdempotencyConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'This idempotency key was already used with a different payload.',
            'PAYMENT_IDEMPOTENCY_CONFLICT',
        );
    }
}
