<?php

declare(strict_types=1);

namespace App\Domains\Payments\DTOs;

final readonly class ProviderPaymentBasketItemData
{
    public function __construct(
        public string $productId,
        public string $description,
        public int $quantity,
        public int $unitPriceMinor,
        public int $unitDiscountMinor,
        public int $lineTotalMinor,
        public ?string $imageUrl = null,
    ) {}
}
