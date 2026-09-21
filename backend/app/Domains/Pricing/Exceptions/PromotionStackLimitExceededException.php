<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class PromotionStackLimitExceededException extends PricingException
{
    public function __construct(string $message = 'Combinable promotion stack limit exceeded.')
    {
        parent::__construct($message, 'PROMOTION_STACK_LIMIT_EXCEEDED');
    }
}
