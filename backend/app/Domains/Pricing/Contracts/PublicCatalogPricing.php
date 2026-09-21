<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Contracts;

use App\Domains\Pricing\DTOs\PublicPriceQuoteData;
use Carbon\CarbonImmutable;

interface PublicCatalogPricing
{
    public function cacheVersion(): int;

    public function defaultPublicPriceListId(string $currencyCode = 'GEL'): ?int;

    public function quoteVariant(int $variantId, int $priceListId, ?CarbonImmutable $effectiveAt = null): ?PublicPriceQuoteData;

    /**
     * @param  list<int>  $variantIds
     * @return array<int, PublicPriceQuoteData>
     */
    public function quoteVariants(array $variantIds, int $priceListId, ?CarbonImmutable $effectiveAt = null): array;

    public function nextBoundaryAt(?CarbonImmutable $from = null): ?CarbonImmutable;

    /**
     * Variant IDs whose published price window or promotion window crosses `$from`..`$to`.
     *
     * @return list<int>
     */
    public function variantIdsWithBoundaryBetween(CarbonImmutable $from, CarbonImmutable $to, int $afterId = 0, int $limit = 500): array;

    /**
     * @return list<int>
     */
    public function variantIdsTargetedByPromotion(int $promotionId, int $afterId = 0, int $limit = 500): array;
}
