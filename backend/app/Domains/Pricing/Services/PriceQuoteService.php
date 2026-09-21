<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\Contracts\CatalogProductLookup;
use App\Domains\Pricing\Exceptions\PriceUnavailableException;
use App\Domains\Pricing\ValueObjects\PriceQuote;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;

final class PriceQuoteService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly EffectiveBasePriceResolver $basePriceResolver,
        private readonly PromotionEligibilityService $eligibility,
        private readonly PromotionCalculator $calculator,
        private readonly CatalogProductLookup $catalog,
    ) {}

    public function quoteVariant(
        int $variantId,
        ?int $priceListId = null,
        ?CarbonImmutable $effectiveAt = null,
    ): PriceQuote {
        $sellable = $this->catalog->sellableRef($variantId);
        if ($sellable === null) {
            throw new PriceUnavailableException('Variant is not available for pricing.');
        }

        $at = $effectiveAt ?? $this->clock->now();
        $baseResult = $this->basePriceResolver->resolveForVariant($variantId, $priceListId, $at);

        $promotions = $this->eligibility->effectivePromotions($baseResult->amount->currencyCode, $at)
            ->filter(fn ($promotion) => $this->eligibility->isEligible($promotion, $sellable, $baseResult->amount->currencyCode));

        $calc = $this->calculator->calculate($baseResult->amount, $promotions);

        return PriceQuote::fromParts(
            variantId: $variantId,
            priceListId: $baseResult->priceListId,
            base: $baseResult->amount,
            final: $calc['final'],
            pricePeriodId: $baseResult->pricePeriodId,
            pricingVersion: $baseResult->pricingVersion,
            calculatedAt: $at,
            appliedPromotions: $calc['applied'],
        );
    }

    public function quoteProductDefaultVariant(int $productId, ?int $priceListId = null): ?PriceQuote
    {
        $default = collect($this->catalog->sellableRefsForProduct($productId))
            ->first(static fn ($ref): bool => $ref->isDefault);

        if ($default === null) {
            return null;
        }

        return $this->quoteVariant($default->variantId, $priceListId);
    }
}
