<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class InvalidMoneyAmountException extends PricingException
{
    public function __construct(string $message = 'Invalid money amount.')
    {
        parent::__construct($message, 'INVALID_MONEY_AMOUNT');
    }
}
