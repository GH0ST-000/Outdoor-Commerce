<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use App\Domains\Inventory\Models\InventoryOperation;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLedgerEntry>
 */
class InventoryLedgerEntryFactory extends Factory
{
    protected $model = InventoryLedgerEntry::class;

    public function definition(): array
    {
        return [
            'inventory_operation_id' => InventoryOperation::factory(),
            'warehouse_id' => Warehouse::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'movement_type' => InventoryMovementType::Receipt,
            'quantity_delta' => 1,
            'on_hand_after' => 1,
            'reserved_after' => 0,
            'created_at' => now(),
        ];
    }
}
