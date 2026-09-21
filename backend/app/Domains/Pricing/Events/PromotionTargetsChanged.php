<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PromotionTargetsChanged
{
    public function __construct(public int $promotionId) {}
}
