<?php

declare(strict_types=1);

namespace App\Domains\Pricing\DTOs;

final readonly class ProductPriceRangeResultData
{
    public function __construct(
        public string $currencyCode,
        public ?int $minAmountMinor,
        public ?int $maxAmountMinor,
        public bool $isRange,
        public int $pricedVariantCount,
        public int $unpricedVariantCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currencyCode,
            'min_amount_minor' => $this->minAmountMinor,
            'max_amount_minor' => $this->maxAmountMinor,
            'is_range' => $this->isRange,
            'priced_variant_count' => $this->pricedVariantCount,
            'unpriced_variant_count' => $this->unpricedVariantCount,
        ];
    }
}
