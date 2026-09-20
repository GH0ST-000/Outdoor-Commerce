<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Support;

final class InventoryLockOrder
{
    /**
     * @param  list<array{warehouse_id: int, product_variant_id: int}>  $pairs
     * @return list<array{warehouse_id: int, product_variant_id: int}>
     */
    public static function sortPairs(array $pairs): array
    {
        usort($pairs, static function (array $a, array $b): int {
            return [$a['warehouse_id'], $a['product_variant_id']] <=> [$b['warehouse_id'], $b['product_variant_id']];
        });

        return $pairs;
    }

    /**
     * @param  list<int>  $warehouseIds
     * @return list<int>
     */
    public static function sortWarehouseIds(array $warehouseIds): array
    {
        $warehouseIds = array_values(array_unique($warehouseIds));
        sort($warehouseIds, SORT_NUMERIC);

        return $warehouseIds;
    }
}
