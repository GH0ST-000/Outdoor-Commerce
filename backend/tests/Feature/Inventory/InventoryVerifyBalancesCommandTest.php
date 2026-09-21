<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Inventory\Enums\InventoryReasonCode;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\InventoryFixtures;

uses(InteractsWithAccessControl::class);

it('reports consistent balances after receipt', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $warehouse = InventoryFixtures::defaultWarehouse();
    $variant = InventoryFixtures::activeVariant();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/receipts', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 3]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ], InventoryFixtures::idempotencyHeader('verify-setup'))->assertCreated();

    $this->artisan('inventory:verify-balances')->assertSuccessful();
});
