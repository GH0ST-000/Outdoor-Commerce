<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Events;

final readonly class InventoryLowStockReached
{
    public function __construct(
        public int $warehouseId,
        public int $productVariantId,
        public int $availableToSell,
        public int $reorderPoint,
    ) {}
}
