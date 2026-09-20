<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
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
    ) {}

    public function quoteVariant(
        ProductVariant $variant,
        ?int $priceListId = null,
        ?CarbonImmutable $effectiveAt = null,
    ): PriceQuote {
        $at = $effectiveAt ?? $this->clock->now();
        $baseResult = $this->basePriceResolver->resolveForVariant($variant->id, $priceListId, $at);

        $variant->loadMissing('product');
        $product = $variant->product;
        $promotions = $this->eligibility->effectivePromotions($baseResult->amount->currencyCode, $at)
            ->filter(fn ($promotion) => $this->eligibility->isEligible($promotion, $variant, $product, $baseResult->amount->currencyCode));

        $calc = $this->calculator->calculate($baseResult->amount, $promotions);

        return PriceQuote::fromParts(
            variantId: $variant->id,
            priceListId: $baseResult->priceListId,
            base: $baseResult->amount,
            final: $calc['final'],
            pricePeriodId: $baseResult->pricePeriodId,
            pricingVersion: $baseResult->pricingVersion,
            calculatedAt: $at,
            appliedPromotions: $calc['applied'],
        );
    }

    public function quoteProductDefaultVariant(Product $product, ?int $priceListId = null): ?PriceQuote
    {
        $product->loadMissing('variants');
        $default = $product->variants->firstWhere('is_default', true);
        if ($default === null) {
            return null;
        }

        return $this->quoteVariant($default, $priceListId);
    }
}
