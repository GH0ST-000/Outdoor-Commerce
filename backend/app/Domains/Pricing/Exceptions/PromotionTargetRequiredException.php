<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Exceptions;

final class PromotionTargetRequiredException extends PricingException
{
    public function __construct(string $message = 'Promotion requires at least one inclusion target or all-products scope.')
    {
        parent::__construct($message, 'PROMOTION_TARGET_REQUIRED');
    }
}
