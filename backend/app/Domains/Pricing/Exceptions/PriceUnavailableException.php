<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class PriceUnavailableException extends PricingException
{
    public function __construct(string $message = 'No effective price is available.')
    {
        parent::__construct($message, 'PRICE_UNAVAILABLE');
    }
}
