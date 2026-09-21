<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class PricePeriodOverlapException extends PricingStateConflictException
{
    public function __construct(string $message = 'Published price periods overlap.')
    {
        parent::__construct($message, 'PRICE_PERIOD_OVERLAP');
    }
}
