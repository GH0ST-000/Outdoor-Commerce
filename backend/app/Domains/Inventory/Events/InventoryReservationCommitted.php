<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Events;

final readonly class InventoryReservationCommitted
{
    public function __construct(
        public int $reservationId,
        public int $operationId,
        public int $warehouseId,
        public int $productVariantId,
        public int $quantity,
    ) {}
}
