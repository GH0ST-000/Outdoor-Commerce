<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class PriceListInactiveException extends PricingStateConflictException
{
    public function __construct(string $message = 'Price list is not active for effective pricing.')
    {
        parent::__construct($message, 'PRICE_LIST_INACTIVE');
    }
}
