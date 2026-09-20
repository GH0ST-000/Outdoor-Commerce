<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Events;

final readonly class InventoryAdjusted
{
    public function __construct(
        public int $operationId,
        public int $warehouseId,
        public int $productVariantId,
    ) {}
}
