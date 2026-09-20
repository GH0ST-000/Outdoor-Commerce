<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Pricing\Exceptions\PriceUnavailableException;

final class PricingReadinessService
{
    public function __construct(
        private readonly EffectiveBasePriceResolver $basePrices,
        private readonly ProductPriceRangeResolver $ranges,
        private readonly CurrencyCatalog $currencies,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public function warningsFor(Product $product): array
    {
        $product->loadMissing('variants');
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

        $defaultVariant = $product->variants->firstWhere('is_default', true);
        if ($defaultVariant !== null && $defaultVariant->status === ProductVariantStatus::Active) {
            try {
                $this->basePrices->resolveForVariant($defaultVariant->id, $list->id);
            } catch (PriceUnavailableException) {
                $warnings['pricing.default_variant'] = ['Default variant has no effective GEL retail price.'];
            }
        }

        $range = $this->ranges->resolve($product, $list->id);
        if ($range->unpricedVariantCount > 0) {
            $warnings['pricing.unpriced_variants'] = [
                "{$range->unpricedVariantCount} active variant(s) lack an effective price in the default list.",
            ];
        }

        return $warnings;
    }
}
