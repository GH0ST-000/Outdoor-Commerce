<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Inventory\DTOs\PublicAvailabilityData;
use App\Domains\Inventory\Enums\WarehouseStatus;
use App\Domains\Inventory\Models\InventoryBalance;
use Illuminate\Support\Facades\DB;

final class EloquentPublicInventoryAvailability implements PublicInventoryAvailability
{
    public function __construct(
        private readonly InventoryCache $inventoryCache,
    ) {}

    public function cacheVersion(): int
    {
        return $this->inventoryCache->version();
    }

    public function forVariant(int $variantId): PublicAvailabilityData
    {
        $map = $this->forVariants([$variantId]);

        return $map[$variantId] ?? new PublicAvailabilityData($variantId, 0, false);
    }

    /**
     * @param  list<int>  $variantIds
     * @return array<int, PublicAvailabilityData>
     */
    public function forVariants(array $variantIds): array
    {
        $ids = array_values(array_unique(array_filter($variantIds, static fn (int $id): bool => $id > 0)));
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = new PublicAvailabilityData($id, 0, false);
        }

        if ($ids === []) {
            return $result;
        }

        $available = '(CASE WHEN inventory_balances.on_hand - inventory_balances.reserved - inventory_balances.safety_stock > 0 THEN inventory_balances.on_hand - inventory_balances.reserved - inventory_balances.safety_stock ELSE 0 END)';

        $rows = InventoryBalance::query()
            ->select([
                'inventory_balances.product_variant_id',
                DB::raw("SUM{$available} as available_to_sell"),
                DB::raw("MAX(CASE WHEN {$available} > 0 AND {$available} <= inventory_balances.reorder_point THEN 1 ELSE 0 END) as is_low_stock"),
            ])
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->whereIn('inventory_balances.product_variant_id', $ids)
            ->where('warehouses.status', WarehouseStatus::Active->value)
            ->whereNull('warehouses.deleted_at')
            ->groupBy('inventory_balances.product_variant_id')
            ->get();

        foreach ($rows as $row) {
            $variantId = (int) $row->product_variant_id;
            $available = (int) $row->getAttribute('available_to_sell');
            $result[$variantId] = new PublicAvailabilityData(
                variantId: $variantId,
                availableToSell: $available,
                isLowStock: $available > 0 && (int) $row->getAttribute('is_low_stock') === 1,
            );
        }

        return $result;
    }
}
