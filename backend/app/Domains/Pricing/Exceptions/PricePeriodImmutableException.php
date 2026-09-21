<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class PricePeriodImmutableException extends PricingStateConflictException
{
    public function __construct(string $message = 'Published price history cannot be mutated.')
    {
        parent::__construct($message, 'PRICE_PERIOD_IMMUTABLE');
    }
}
