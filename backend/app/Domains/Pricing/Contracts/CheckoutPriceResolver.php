<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Contracts;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\ValueObjects\PriceQuote;

interface CheckoutPriceResolver
{
    public function resolveVariantQuote(ProductVariant $variant, ?int $priceListId = null): PriceQuote;
}
