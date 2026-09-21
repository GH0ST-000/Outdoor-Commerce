<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use Carbon\CarbonImmutable;

final readonly class PromotionWriteData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public DiscountType $discountType,
        public ?int $percentageBasisPoints,
        public ?int $fixedAmountMinor,
        public ?string $currencyCode,
        public int $priority,
        public PromotionStackingMode $stackingMode,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public ?int $maximumDiscountMinor,
    ) {}
}
