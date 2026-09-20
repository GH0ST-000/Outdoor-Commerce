<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class DefaultPriceListMissingException extends PricingException
{
    public function __construct(string $message = 'Default price list is missing.')
    {
        parent::__construct($message, 'DEFAULT_PRICE_LIST_MISSING');
    }
}
