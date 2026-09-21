<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Actions\ReceiveInventoryAction;
use App\Domains\Inventory\Actions\ReserveInventoryAction;
use App\Domains\Inventory\DTOs\ReceiveInventoryData;
use App\Domains\Inventory\DTOs\ReserveInventoryData;
use App\Domains\Inventory\Enums\InventoryReasonCode;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\InventoryBalanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local demo inventory only — run after CatalogDemoSeeder.
 */
final class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $actor = User::query()->first();
        if ($actor === null) {
            return;
        }

        $main = Warehouse::query()->firstOrCreate(
            ['code' => 'TBS-MAIN'],
            ['name' => 'Tbilisi Main Warehouse', 'is_default' => true],
        );

        Warehouse::query()->firstOrCreate(
            ['code' => 'KUT-STORE'],
            ['name' => 'Kutaisi Store', 'is_default' => false],
        );

        $variants = ProductVariant::query()->active()->limit(5)->get();
        if ($variants->isEmpty()) {
            return;
        }

        $receive = app(ReceiveInventoryAction::class);
        $reserve = app(ReserveInventoryAction::class);
        $balances = app(InventoryBalanceService::class);

        foreach ($variants as $index => $variant) {
            $qty = 20 - ($index * 4);
            $receive->execute(
                new ReceiveInventoryData(
                    warehouseId: (int) $main->id,
                    items: [['product_variant_id' => $variant->id, 'quantity' => max(0, $qty)]],
                    reasonCode: InventoryReasonCode::InitialCount,
                    referenceType: 'demo_seed',
                    referenceId: 'INV-DEMO',
                    note: 'Inventory demo seed',
                    idempotencyKey: 'demo-receipt-'.$variant->id,
                ),
                $actor,
            );

            $balance = $balances->lockForUpdate((int) $main->id, (int) $variant->id);
            $balance->reorder_point = 5;
            $balance->safety_stock = $index === 0 ? 2 : 0;
            $balance->save();

            if ($index === 2 && $qty > 0) {
                $reserve->execute(
                    new ReserveInventoryData(
                        productVariantId: $variant->id,
                        quantity: 1,
                        warehouseId: (int) $main->id,
                        referenceType: 'demo_cart',
                        referenceId: (string) Str::uuid(),
                        expiresAt: now()->addMinutes(15),
                        idempotencyKey: 'demo-res-'.$variant->id,
                    ),
                    $actor,
                );
            }
        }
    }
}
