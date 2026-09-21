<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class PromotionNotReadyException extends PricingException
{
    public function __construct(string $message = 'Promotion is not ready to activate.')
    {
        parent::__construct($message, 'PROMOTION_NOT_READY');
    }
}
