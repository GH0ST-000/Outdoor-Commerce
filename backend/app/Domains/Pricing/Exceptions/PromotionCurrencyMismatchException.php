<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class PromotionCurrencyMismatchException extends PricingStateConflictException
{
    public function __construct(string $message = 'Promotion currency does not match price currency.')
    {
        parent::__construct($message, 'PROMOTION_CURRENCY_MISMATCH');
    }
}
