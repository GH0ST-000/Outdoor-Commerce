<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

use Carbon\CarbonImmutable;

/**
 * Cross-module public catalog quote. Does not replace checkout revalidation.
 */
final readonly class PublicPriceQuoteData
{
    /**
     * @param  list<array{code: string, name: string, discount_type: string}>  $appliedPromotions
     */
    public function __construct(
        public int $variantId,
        public int $priceListId,
        public string $currencyCode,
        public int $baseAmountMinor,
        public int $finalAmountMinor,
        public int $discountAmountMinor,
        public int $pricingVersion,
        public CarbonImmutable $calculatedAt,
        public array $appliedPromotions = [],
        public ?string $signature = null,
    ) {}

    public function onSale(): bool
    {
        return $this->finalAmountMinor < $this->baseAmountMinor;
    }
}
