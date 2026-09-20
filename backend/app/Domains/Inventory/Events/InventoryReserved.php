<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Events;

final readonly class InventoryReserved
{
    public function __construct(
        public int $reservationId,
        public int $warehouseId,
        public int $productVariantId,
        public int $quantity,
    ) {}
}
