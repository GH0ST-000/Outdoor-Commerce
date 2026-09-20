<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

class PricingStateConflictException extends DomainException
{
    public function __construct(
        string $message = 'Pricing state conflict.',
        string $errorCode = 'PRICING_STATE_CONFLICT',
    ) {
        parent::__construct($message, $errorCode, 409);
    }
}
