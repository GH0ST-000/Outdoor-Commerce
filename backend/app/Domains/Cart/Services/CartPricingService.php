<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Pricing\DTOs\PublicPriceQuoteData;

final class CartPricingService
{
    public function __construct(
        private readonly PublicCatalogPricing $pricing,
    ) {}

    /**
     * @param  list<int>  $variantIds
     * @return array<int, PublicPriceQuoteData>
     */
    public function quotesFor(array $variantIds, int $priceListId): array
    {
        $ids = array_values(array_unique(array_filter($variantIds, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        return $this->pricing->quoteVariants($ids, $priceListId);
    }
}
