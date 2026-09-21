<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PromotionActivated
{
    public function __construct(public int $promotionId) {}
}
