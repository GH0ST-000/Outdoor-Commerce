<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Exceptions\VariantInactiveException;
use App\Domains\Inventory\Exceptions\WarehouseInactiveException;
use App\Domains\Inventory\Models\Warehouse;

final class InventoryGuardService
{
    public function requireActiveWarehouse(Warehouse|int $warehouse): Warehouse
    {
        if (! $warehouse instanceof Warehouse) {
            $warehouse = Warehouse::query()->findOrFail($warehouse);
        }

        if (! $warehouse->allowsStockOperations()) {
            throw new WarehouseInactiveException;
        }

        return $warehouse;
    }

    public function requireActiveVariant(ProductVariant|int $variant): ProductVariant
    {
        if (! $variant instanceof ProductVariant) {
            $variant = ProductVariant::query()->findOrFail($variant);
        }

        if ($variant->trashed() || $variant->status !== ProductVariantStatus::Active) {
            throw new VariantInactiveException;
        }

        return $variant;
    }
}
