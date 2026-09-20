<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBalance>
 */
final class InventoryBalanceFactory extends Factory
{
    protected $model = InventoryBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'on_hand' => 0,
            'reserved' => 0,
            'safety_stock' => 0,
            'reorder_point' => 0,
            'version' => 0,
            'last_movement_at' => null,
        ];
    }
}
