<?php

declare(strict_types=1);

namespace App\Domains\Cart\DTOs;

final readonly class CartLinePricingData
{
    public function __construct(
        public int $unitPriceMinor,
        public int $compareAtPriceMinor,
        public int $lineSubtotalMinor,
        public int $lineDiscountMinor,
        public int $lineTotalMinor,
        public string $currency,
        public bool $priceChanged,
        public ?string $pricingSignature = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'unit_price_minor' => $this->unitPriceMinor,
            'compare_at_price_minor' => $this->compareAtPriceMinor,
            'line_subtotal_minor' => $this->lineSubtotalMinor,
            'line_discount_minor' => $this->lineDiscountMinor,
            'line_total_minor' => $this->lineTotalMinor,
            'currency' => $this->currency,
            'price_changed' => $this->priceChanged,
            'pricing_signature' => $this->pricingSignature,
        ];
    }
}
