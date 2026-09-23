<?php

declare(strict_types=1);

namespace App\Domains\Orders\Exceptions;

final class OrderIdempotencyConflictException extends OrderException
{
    public function __construct()
    {
        parent::__construct(
            'This idempotency key was already used with a different request.',
            'ORDER_IDEMPOTENCY_CONFLICT',
        );
    }
}
