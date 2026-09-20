<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\Contracts\CatalogProductLookup;
use App\Domains\Pricing\Contracts\ProductPricingReadiness;
use App\Domains\Pricing\Exceptions\PriceUnavailableException;

final class PricingReadinessService implements ProductPricingReadiness
{
    public function __construct(
        private readonly EffectiveBasePriceResolver $basePrices,
        private readonly ProductPriceRangeResolver $ranges,
        private readonly CurrencyCatalog $currencies,
        private readonly CatalogProductLookup $catalog,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public function warningsForProduct(int $productId): array
    {
        /** @var array<string, list<string>> $warnings */
        $warnings = [];

        $currency = $this->currencies->defaultCode();
        try {
            $list = $this->basePrices->resolvePriceList(null);
        } catch (\Throwable) {
            $warnings['pricing.default_list'] = ['Default price list is not configured.'];

            return $warnings;
        }

        if ($list->currency_code !== $currency) {
            $warnings['pricing.currency'] = ["Default list currency is {$list->currency_code}, expected {$currency}."];
        }

        $refs = $this->catalog->sellableRefsForProduct($productId);
        $defaultVariant = collect($refs)->first(
            static fn ($ref): bool => $ref->isDefault && $ref->variantActive,
        );

        if ($defaultVariant !== null) {
            try {
                $this->basePrices->resolveForVariant($defaultVariant->variantId, $list->id);
            } catch (PriceUnavailableException) {
                $warnings['pricing.default_variant'] = ['Default variant has no effective GEL retail price.'];
            }
        }

        $range = $this->ranges->resolve($productId, $list->id);
        if ($range->unpricedVariantCount > 0) {
            $warnings['pricing.unpriced_variants'] = [
                "{$range->unpricedVariantCount} active variant(s) lack an effective price in the default list.",
            ];
        }

        return $warnings;
    }
}
