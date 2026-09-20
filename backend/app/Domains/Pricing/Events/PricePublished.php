<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PricePublished
{
    public function __construct(public int $pricePeriodId, public int $variantPriceId) {}
}
