<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Shipping\Exceptions\ShipmentException;

final class WarehouseAllocationService
{
    public function resolve(Order $order, ?string $warehouseCode, OrderItem $sampleItem): Warehouse
    {
        $eligibleIds = $this->eligibleWarehouseIds($order);

        if ($eligibleIds === []) {
            throw ShipmentException::warehouseInvalid();
        }

        if ($warehouseCode !== null && $warehouseCode !== '') {
            $warehouse = Warehouse::query()->where('code', $warehouseCode)->first();
            if ($warehouse === null || ! in_array($warehouse->id, $eligibleIds, true)) {
                throw ShipmentException::warehouseInvalid();
            }

            return $warehouse;
        }

        $itemWarehouses = $this->warehouseIdsForItem($order, $sampleItem);
        if (count($itemWarehouses) === 1) {
            $warehouse = Warehouse::query()->find($itemWarehouses[0]);
            if ($warehouse instanceof Warehouse) {
                return $warehouse;
            }
        }

        if (count($eligibleIds) === 1) {
            $warehouse = Warehouse::query()->find($eligibleIds[0]);
            if ($warehouse instanceof Warehouse) {
                return $warehouse;
            }
        }

        throw ShipmentException::warehouseInvalid();
    }

    /**
     * @return list<int>
     */
    public function eligibleWarehouseIds(Order $order): array
    {
        return InventoryReservation::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $order->public_id)
            ->where('status', InventoryReservationStatus::Committed)
            ->orderBy('warehouse_id')
            ->pluck('warehouse_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function warehouseIdsForItem(Order $order, OrderItem $item): array
    {
        if ($item->variant_id === null) {
            return $this->eligibleWarehouseIds($order);
        }

        return InventoryReservation::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $order->public_id)
            ->where('product_variant_id', $item->variant_id)
            ->where('status', InventoryReservationStatus::Committed)
            ->orderBy('warehouse_id')
            ->pluck('warehouse_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<OrderItem>  $items
     */
    public function assertItemsBelongToWarehouse(Order $order, Warehouse $warehouse, array $items): void
    {
        foreach ($items as $item) {
            $ids = $this->warehouseIdsForItem($order, $item);
            if ($ids !== [] && ! in_array($warehouse->id, $ids, true)) {
                throw ShipmentException::warehouseInvalid();
            }
        }
    }
}
