<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Events;

final readonly class InventoryTransferred
{
    public function __construct(
        public int $operationId,
        public int $sourceWarehouseId,
        public int $destinationWarehouseId,
    ) {}
}
