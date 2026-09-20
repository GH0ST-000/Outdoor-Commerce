<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Events\InventoryLowStockReached;
use App\Domains\Inventory\Events\InventoryOutOfStock;
use App\Domains\Inventory\Events\InventoryStockRecovered;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\ValueObjects\InventoryQuantities;
use Illuminate\Support\Facades\DB;

/**
 * Emits crossing events only — not on every mutation while remaining low.
 */
final class InventoryLowStockService
{
    /**
     * @param  list<callable(): mixed>  $afterCommit
     */
    public function evaluate(
        InventoryBalance $balance,
        ?InventoryQuantities $previous,
        array &$afterCommit,
    ): void {
        $current = $balance->quantities();
        $wasLow = $previous?->isLowStock() ?? false;
        $wasOut = $previous?->isOutOfStock() ?? false;
        $isLow = $current->isLowStock();
        $isOut = $current->isOutOfStock();

        if ($isOut && ! $wasOut) {
            $afterCommit[] = fn () => event(new InventoryOutOfStock(
                $balance->warehouse_id,
                $balance->product_variant_id,
            ));
        }

        if ($isLow && ! $wasLow) {
            $afterCommit[] = fn () => event(new InventoryLowStockReached(
                $balance->warehouse_id,
                $balance->product_variant_id,
                $current->availableToSell(),
                $current->reorderPoint,
            ));
        }

        if (! $isLow && $wasLow) {
            $afterCommit[] = fn () => event(new InventoryStockRecovered(
                $balance->warehouse_id,
                $balance->product_variant_id,
                $current->availableToSell(),
            ));
        }
    }

    /**
     * @param  list<callable(): void>  $callbacks
     */
    public function registerAfterCommit(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            DB::afterCommit($callback);
        }
    }
}
