<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class StalePriceVersionException extends PricingStateConflictException
{
    public function __construct(string $message = 'Price version is stale. Reload and retry.')
    {
        parent::__construct($message, 'STALE_PRICE_VERSION');
    }
}
