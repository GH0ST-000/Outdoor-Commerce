<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Contracts;

use App\Domains\Pricing\ValueObjects\PriceQuote;

interface CheckoutPriceResolver
{
    public function resolveVariantQuote(int $variantId, ?int $priceListId = null): PriceQuote;
}
