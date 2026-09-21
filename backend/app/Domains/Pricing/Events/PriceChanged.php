<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PriceChanged
{
    public function __construct(public int $variantPriceId, public int $priceListId, public int $variantId) {}
}
