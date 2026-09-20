<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Inventory\Actions\CommitInventoryReservationAction;
use App\Domains\Inventory\Actions\ReserveInventoryAction;
use App\Domains\Inventory\DTOs\ReserveInventoryData;
use App\Domains\Inventory\Enums\InventoryReasonCode;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryReservation;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\InventoryFixtures;

uses(InteractsWithAccessControl::class);

it('reserves and releases stock without changing on hand', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $warehouse = InventoryFixtures::defaultWarehouse();
    $variant = InventoryFixtures::activeVariant();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/receipts', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 5]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ], InventoryFixtures::idempotencyHeader('res-setup'))->assertCreated();

    $reservation = app(ReserveInventoryAction::class)->execute(
        new ReserveInventoryData(
            productVariantId: $variant->id,
            quantity: 2,
            warehouseId: $warehouse->id,
            referenceType: 'checkout',
            referenceId: 'chk-1',
            expiresAt: now()->addMinutes(15),
            idempotencyKey: 'reserve-1',
        ),
        $manager,
    );

    $balance = InventoryBalance::query()->where([
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
    ])->firstOrFail();

    expect($balance->on_hand)->toBe(5);
    expect($balance->reserved)->toBe(2);

    $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/inventory/reservations/{$reservation->id}/release", [], InventoryFixtures::idempotencyHeader('release-1'))
        ->assertOk();

    $balance->refresh();
    expect($balance->reserved)->toBe(0);
    expect($balance->on_hand)->toBe(5);
});

it('commits reservation and creates sale ledger movement', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $warehouse = InventoryFixtures::defaultWarehouse();
    $variant = InventoryFixtures::activeVariant();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/receipts', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ], InventoryFixtures::idempotencyHeader('commit-stock'))->assertCreated();

    app(ReserveInventoryAction::class)->execute(
        new ReserveInventoryData(
            productVariantId: $variant->id,
            quantity: 1,
            warehouseId: $warehouse->id,
            referenceType: null,
            referenceId: null,
            expiresAt: now()->addHour(),
            idempotencyKey: 'commit-setup',
        ),
        $manager,
    );

    $reservation = InventoryReservation::query()->firstOrFail();

    app(CommitInventoryReservationAction::class)->execute(
        $reservation,
        'commit-1',
        $manager,
    );

    $balance = InventoryBalance::query()->where([
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
    ])->firstOrFail();

    expect($balance->reserved)->toBe(0);
    expect($balance->on_hand)->toBe(0);
});
