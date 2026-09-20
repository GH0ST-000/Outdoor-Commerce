<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class CurrencyMismatchException extends PricingStateConflictException
{
    public function __construct(string $message = 'Currency mismatch.')
    {
        parent::__construct($message, 'CURRENCY_MISMATCH');
    }
}
