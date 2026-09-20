<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Contracts\CheckoutPriceResolver;
use App\Domains\Pricing\ValueObjects\PriceQuote;

final class DefaultCheckoutPriceResolver implements CheckoutPriceResolver
{
    public function __construct(
        private readonly PriceQuoteService $quotes,
    ) {}

    public function resolveVariantQuote(ProductVariant $variant, ?int $priceListId = null): PriceQuote
    {
        return $this->quotes->quoteVariant($variant, $priceListId);
    }
}
