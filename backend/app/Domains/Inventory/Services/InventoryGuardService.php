<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Catalog\Contracts\CatalogProductLookup;
use App\Domains\Inventory\Exceptions\VariantInactiveException;
use App\Domains\Inventory\Exceptions\WarehouseInactiveException;
use App\Domains\Inventory\Models\Warehouse;

final class InventoryGuardService
{
    public function __construct(
        private readonly CatalogProductLookup $catalog,
    ) {}

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

    public function requireActiveVariant(int $variantId): int
    {
        $ref = $this->catalog->existingSellableRef($variantId);

        if (! $ref->variantActive) {
            throw new VariantInactiveException;
        }

        return $ref->variantId;
    }
}
