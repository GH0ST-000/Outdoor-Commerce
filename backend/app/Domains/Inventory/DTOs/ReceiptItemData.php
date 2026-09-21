<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

final readonly class ReceiptItemData
{
    public function __construct(
        public int $productVariantId,
        public int $quantity,
    ) {}
}
