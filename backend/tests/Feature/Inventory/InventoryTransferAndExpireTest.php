<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Inventory\Actions\ExpireInventoryReservationsAction;
use App\Domains\Inventory\Actions\ReserveInventoryAction;
use App\Domains\Inventory\DTOs\ReserveInventoryData;
use App\Domains\Inventory\Enums\InventoryReasonCode;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\InventoryFixtures;

uses(InteractsWithAccessControl::class);

it('transfers stock atomically between warehouses', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $source = InventoryFixtures::defaultWarehouse();
    $destination = InventoryFixtures::secondWarehouse();
    $variant = InventoryFixtures::activeVariant();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/receipts', [
        'warehouse_id' => $source->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 8]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ], InventoryFixtures::idempotencyHeader('xfer-setup'))->assertCreated();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/transfers', [
        'source_warehouse_id' => $source->id,
        'destination_warehouse_id' => $destination->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 3]],
        'note' => 'Store restock',
    ], InventoryFixtures::idempotencyHeader('xfer-1'))->assertCreated();

    expect(InventoryBalance::query()->where([
        'warehouse_id' => $source->id,
        'product_variant_id' => $variant->id,
    ])->value('on_hand'))->toBe(5);

    expect(InventoryBalance::query()->where([
        'warehouse_id' => $destination->id,
        'product_variant_id' => $variant->id,
    ])->value('on_hand'))->toBe(3);

    expect(InventoryLedgerEntry::query()->count())->toBe(3); // receipt + out + in
});

it('expires overdue active reservations and frees reserved quantity', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $warehouse = InventoryFixtures::defaultWarehouse();
    $variant = InventoryFixtures::activeVariant();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/receipts', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 4]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ], InventoryFixtures::idempotencyHeader('exp-setup'))->assertCreated();

    $reservation = app(ReserveInventoryAction::class)->execute(
        new ReserveInventoryData(
            productVariantId: $variant->id,
            quantity: 2,
            warehouseId: $warehouse->id,
            referenceType: 'checkout',
            referenceId: 'chk-exp',
            expiresAt: now()->subMinute(),
            idempotencyKey: 'reserve-exp-1',
        ),
        $manager,
    );

    expect(app(ExpireInventoryReservationsAction::class)->execute())->toBe(1);

    expect($reservation->fresh()->status)->toBe(InventoryReservationStatus::Expired);
    expect(InventoryBalance::query()->where([
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
    ])->value('reserved'))->toBe(0);

    expect(app(ExpireInventoryReservationsAction::class)->execute())->toBe(0);
});
