<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Pricing\DTOs\ProductPriceRangeResult;
use App\Domains\Pricing\Exceptions\PriceUnavailableException;
use Carbon\CarbonImmutable;

final class ProductPriceRangeResolver
{
    public function __construct(
        private readonly EffectiveBasePriceResolver $basePriceResolver,
    ) {}

    public function resolve(Product $product, ?int $priceListId = null, ?CarbonImmutable $effectiveAt = null): ProductPriceRangeResult
    {
        $product->loadMissing('variants');
        $activeVariants = $product->variants->where('status', ProductVariantStatus::Active);

        $currency = null;
        $min = null;
        $max = null;
        $priced = 0;
        $unpriced = 0;

        foreach ($activeVariants as $variant) {
            try {
                $base = $this->basePriceResolver->resolveForVariant($variant->id, $priceListId, $effectiveAt);
            } catch (PriceUnavailableException) {
                $unpriced++;

                continue;
            }

            $amount = $base->amount->amountMinor;
            $currency ??= $base->amount->currencyCode;
            $min = $min === null ? $amount : min($min, $amount);
            $max = $max === null ? $amount : max($max, $amount);
            $priced++;
        }

        if ($currency === null) {
            return new ProductPriceRangeResult(
                currencyCode: app(CurrencyCatalog::class)->defaultCode(),
                minAmountMinor: null,
                maxAmountMinor: null,
                isRange: false,
                pricedVariantCount: 0,
                unpricedVariantCount: $unpriced,
            );
        }

        return new ProductPriceRangeResult(
            currencyCode: $currency,
            minAmountMinor: $min,
            maxAmountMinor: $max,
            isRange: $min !== null && $max !== null && $min !== $max,
            pricedVariantCount: $priced,
            unpricedVariantCount: $unpriced,
        );
    }
}
