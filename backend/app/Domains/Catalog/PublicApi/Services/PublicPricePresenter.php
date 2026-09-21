<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Pricing\DTOs\PublicPriceQuoteData;
use Carbon\CarbonImmutable;

final class PublicPricePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function variant(PublicPriceQuoteData $quote): array
    {
        return [
            'currency' => $quote->currencyCode,
            'base_amount_minor' => $quote->baseAmountMinor,
            'final_amount_minor' => $quote->finalAmountMinor,
            'discount_amount_minor' => $quote->discountAmountMinor,
            'on_sale' => $quote->onSale(),
            'applied_promotions' => $quote->appliedPromotions,
            'calculated_at' => $quote->calculatedAt->toIso8601String(),
            'signature' => $quote->signature,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productRange(
        string $currency,
        ?int $minFinal,
        ?int $maxFinal,
        ?int $minBase,
        bool $onSale,
    ): array {
        $isRange = $minFinal !== null && $maxFinal !== null && $minFinal !== $maxFinal;

        return [
            'currency' => $currency,
            'min_final_amount_minor' => $minFinal,
            'max_final_amount_minor' => $maxFinal,
            'min_base_amount_minor' => $minBase,
            'is_range' => $isRange,
            'on_sale' => $onSale,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function missing(string $currency, CarbonImmutable $at): array
    {
        return [
            'currency' => $currency,
            'base_amount_minor' => null,
            'final_amount_minor' => null,
            'discount_amount_minor' => null,
            'on_sale' => false,
            'applied_promotions' => [],
            'calculated_at' => $at->toIso8601String(),
            'signature' => null,
        ];
    }
}
