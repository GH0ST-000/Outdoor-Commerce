<?php

declare(strict_types=1);

namespace App\Domains\Pricing\ValueObjects;

use App\Domains\Pricing\Support\PricingSignature;
use Carbon\CarbonImmutable;

final readonly class PriceQuote
{
    /**
     * @param  list<array<string, mixed>>  $appliedPromotions
     */
    public function __construct(
        public int $variantId,
        public int $priceListId,
        public string $currencyCode,
        public int $baseAmountMinor,
        public int $finalAmountMinor,
        public int $discountAmountMinor,
        public ?int $pricePeriodId,
        public int $pricingVersion,
        public CarbonImmutable $calculatedAt,
        public array $appliedPromotions = [],
        public ?string $pricingSignature = null,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $appliedPromotions
     */
    public static function fromParts(
        int $variantId,
        int $priceListId,
        Money $base,
        Money $final,
        ?int $pricePeriodId,
        int $pricingVersion,
        CarbonImmutable $calculatedAt,
        array $appliedPromotions = [],
    ): self {
        $discountMinor = max(0, $base->amountMinor - $final->amountMinor);

        $signature = PricingSignature::hash([
            'variant_id' => $variantId,
            'price_list_id' => $priceListId,
            'currency' => $base->currencyCode,
            'base_amount_minor' => $base->amountMinor,
            'final_amount_minor' => $final->amountMinor,
            'price_period_id' => $pricePeriodId,
            'pricing_version' => $pricingVersion,
            'applied_promotions' => $appliedPromotions,
            'calculated_at' => $calculatedAt->toIso8601String(),
        ]);

        return new self(
            variantId: $variantId,
            priceListId: $priceListId,
            currencyCode: $base->currencyCode,
            baseAmountMinor: $base->amountMinor,
            finalAmountMinor: $final->amountMinor,
            discountAmountMinor: $discountMinor,
            pricePeriodId: $pricePeriodId,
            pricingVersion: $pricingVersion,
            calculatedAt: $calculatedAt,
            appliedPromotions: $appliedPromotions,
            pricingSignature: $signature,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'variant_id' => $this->variantId,
            'price_list_id' => $this->priceListId,
            'currency' => $this->currencyCode,
            'base_amount_minor' => $this->baseAmountMinor,
            'final_amount_minor' => $this->finalAmountMinor,
            'discount_amount_minor' => $this->discountAmountMinor,
            'price_period_id' => $this->pricePeriodId,
            'pricing_version' => $this->pricingVersion,
            'calculated_at' => $this->calculatedAt->toIso8601String(),
            'applied_promotions' => $this->appliedPromotions,
            'pricing_signature' => $this->pricingSignature,
        ];
    }
}
