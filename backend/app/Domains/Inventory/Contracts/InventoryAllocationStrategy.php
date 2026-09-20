<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\Models\Warehouse;

interface InventoryAllocationStrategy
{
    public function resolveWarehouse(?int $warehouseId, int $variantId, int $quantity): Warehouse;
}
