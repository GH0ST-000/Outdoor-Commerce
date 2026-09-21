<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Exceptions\BalanceInvariantViolationException;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Exceptions\InsufficientUnreservedStockException;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use App\Domains\Inventory\Models\InventoryOperation;

final class InventoryLedgerService
{
    public function applyPhysicalDelta(
        InventoryOperation $operation,
        InventoryBalance $balance,
        InventoryMovementType $movementType,
        int $quantityDelta,
    ): InventoryLedgerEntry {
        if ($quantityDelta === 0) {
            throw new BalanceInvariantViolationException('Physical movement delta cannot be zero.');
        }

        $newOnHand = $balance->on_hand + $quantityDelta;

        if (! config('inventory.allow_negative_stock', false) && $newOnHand < 0) {
            throw new InsufficientStockException;
        }

        if ($newOnHand < $balance->reserved) {
            throw new BalanceInvariantViolationException('On-hand cannot fall below reserved quantity.');
        }

        $balance->on_hand = $newOnHand;
        $balance->version = $balance->version + 1;
        $balance->last_movement_at = now();
        $balance->save();

        return InventoryLedgerEntry::query()->create([
            'inventory_operation_id' => $operation->id,
            'warehouse_id' => $balance->warehouse_id,
            'product_variant_id' => $balance->product_variant_id,
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
            'on_hand_after' => $balance->on_hand,
            'reserved_after' => $balance->reserved,
        ]);
    }

    public function applyReservedDelta(InventoryBalance $balance, int $delta): void
    {
        $newReserved = $balance->reserved + $delta;

        if ($newReserved < 0) {
            throw new BalanceInvariantViolationException('Reserved quantity cannot be negative.');
        }

        if ($newReserved > $balance->on_hand) {
            throw new InsufficientStockException('Reserved quantity cannot exceed on-hand.');
        }

        $balance->reserved = $newReserved;
        $balance->version = $balance->version + 1;
        $balance->save();
    }

    public function assertUnreservedAvailable(InventoryBalance $balance, int $quantity): void
    {
        if ($balance->quantities()->unreserved() < $quantity) {
            throw new InsufficientUnreservedStockException;
        }
    }

    public function assertAvailableToSell(InventoryBalance $balance, int $quantity): void
    {
        if ($balance->quantities()->availableToSell() < $quantity) {
            throw new InsufficientStockException;
        }
    }
}
