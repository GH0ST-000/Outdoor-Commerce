<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;

final readonly class PromotionTargetData
{
    public function __construct(
        public PromotionTargetType $targetType,
        public ?int $targetId,
        public PromotionTargetMode $mode,
    ) {}
}
