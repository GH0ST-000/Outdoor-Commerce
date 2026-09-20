<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Exceptions\StaleInventoryVersionException;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Support\InventoryLockOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

final class InventoryBalanceService
{
    /**
     * @param  list<array{warehouse_id: int, product_variant_id: int}>  $pairs
     * @return Collection<string, InventoryBalance>
     */
    public function lockMany(array $pairs): Collection
    {
        $pairs = InventoryLockOrder::sortPairs($pairs);
        $balances = collect();

        foreach ($pairs as $pair) {
            $key = $pair['warehouse_id'].'-'.$pair['product_variant_id'];
            $balances->put($key, $this->lockForUpdate($pair['warehouse_id'], $pair['product_variant_id']));
        }

        return $balances;
    }

    public function lockForUpdate(int $warehouseId, int $variantId): InventoryBalance
    {
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        if ($balance !== null) {
            return $balance;
        }

        try {
            InventoryBalance::query()->create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'on_hand' => 0,
                'reserved' => 0,
                'safety_stock' => 0,
                'reorder_point' => 0,
                'version' => 0,
            ]);
        } catch (QueryException) {
            // Concurrent first insert — unique constraint wins.
        }

        return InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function assertExpectedVersion(InventoryBalance $balance, ?int $expectedVersion): void
    {
        if ($expectedVersion === null) {
            return;
        }

        if ($balance->version !== $expectedVersion) {
            throw new StaleInventoryVersionException;
        }
    }
}
