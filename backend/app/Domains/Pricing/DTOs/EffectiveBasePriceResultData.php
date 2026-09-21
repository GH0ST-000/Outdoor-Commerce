<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

use App\Domains\Pricing\ValueObjects\Money;
use Carbon\CarbonImmutable;

final readonly class EffectiveBasePriceResultData
{
    public function __construct(
        public Money $amount,
        public int $priceListId,
        public int $pricePeriodId,
        public int $variantPriceId,
        public int $pricingVersion,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
    ) {}
}
