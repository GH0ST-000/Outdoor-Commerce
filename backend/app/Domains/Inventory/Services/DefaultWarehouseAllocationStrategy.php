<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Contracts\InventoryAllocationStrategy;
use App\Domains\Inventory\Enums\WarehouseStatus;
use App\Domains\Inventory\Exceptions\DefaultWarehouseMissingException;
use App\Domains\Inventory\Exceptions\WarehouseInactiveException;
use App\Domains\Inventory\Models\Warehouse;

final class DefaultWarehouseAllocationStrategy implements InventoryAllocationStrategy
{
    public function resolveWarehouse(?int $warehouseId, int $variantId, int $quantity): Warehouse
    {
        unset($variantId, $quantity);

        $warehouse = $warehouseId !== null
            ? Warehouse::query()->find($warehouseId)
            : Warehouse::query()
                ->where('is_default', true)
                ->where('status', WarehouseStatus::Active->value)
                ->whereNull('deleted_at')
                ->first();

        if ($warehouse === null) {
            throw new DefaultWarehouseMissingException;
        }

        if (! $warehouse->allowsStockOperations()) {
            throw new WarehouseInactiveException;
        }

        return $warehouse;
    }
}
