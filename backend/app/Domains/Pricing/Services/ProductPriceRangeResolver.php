<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\Contracts\CatalogProductLookup;
use App\Domains\Pricing\DTOs\ProductPriceRangeResultData;
use App\Domains\Pricing\Exceptions\PriceUnavailableException;
use Carbon\CarbonImmutable;

final class ProductPriceRangeResolver
{
    public function __construct(
        private readonly EffectiveBasePriceResolver $basePriceResolver,
        private readonly CatalogProductLookup $catalog,
    ) {}

    public function resolve(int $productId, ?int $priceListId = null, ?CarbonImmutable $effectiveAt = null): ProductPriceRangeResultData
    {
        $activeVariants = array_values(array_filter(
            $this->catalog->sellableRefsForProduct($productId),
            static fn ($ref): bool => $ref->variantActive && ! $ref->variantDeleted,
        ));

        $currency = null;
        $min = null;
        $max = null;
        $priced = 0;
        $unpriced = 0;

        foreach ($activeVariants as $variant) {
            try {
                $base = $this->basePriceResolver->resolveForVariant($variant->variantId, $priceListId, $effectiveAt);
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
            return new ProductPriceRangeResultData(
                currencyCode: app(CurrencyCatalog::class)->defaultCode(),
                minAmountMinor: null,
                maxAmountMinor: null,
                isRange: false,
                pricedVariantCount: 0,
                unpricedVariantCount: $unpriced,
            );
        }

        return new ProductPriceRangeResultData(
            currencyCode: $currency,
            minAmountMinor: $min,
            maxAmountMinor: $max,
            isRange: $min !== null && $max !== null && $min !== $max,
            pricedVariantCount: $priced,
            unpricedVariantCount: $unpriced,
        );
    }
}
