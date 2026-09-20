<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Inventory\Enums\InventoryReasonCode;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use App\Domains\Inventory\Models\InventoryOperation;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\InventoryFixtures;

uses(InteractsWithAccessControl::class);

it('receives stock idempotently', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $warehouse = InventoryFixtures::defaultWarehouse();
    $variant = InventoryFixtures::activeVariant();

    $payload = [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 10]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ];

    $headers = InventoryFixtures::idempotencyHeader('receipt-1');

    $this->actingAs($manager, 'web')
        ->postJson('/api/v1/admin/inventory/receipts', $payload, $headers)
        ->assertCreated();

    $this->actingAs($manager, 'web')
        ->postJson('/api/v1/admin/inventory/receipts', $payload, $headers)
        ->assertOk()
        ->assertJsonPath('meta.replay', true);

    expect(InventoryOperation::query()->count())->toBe(1);
    expect(InventoryLedgerEntry::query()->count())->toBe(1);

    $balance = InventoryBalance::query()->where([
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
    ])->first();

    expect($balance?->on_hand)->toBe(10);
    expect(AuditLog::query()->where('event', AuditEvent::InventoryReceived->value)->count())->toBe(1);
});

it('rejects duplicate idempotency key with different payload', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $warehouse = InventoryFixtures::defaultWarehouse();
    $variant = InventoryFixtures::activeVariant();
    $headers = InventoryFixtures::idempotencyHeader('conflict-1');

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/receipts', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 5]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ], $headers)->assertCreated();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/receipts', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 6]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ], $headers)->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
});

it('adjusts stock and rejects stale version', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $warehouse = InventoryFixtures::defaultWarehouse();
    $variant = InventoryFixtures::activeVariant();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/receipts', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 10]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ], InventoryFixtures::idempotencyHeader('adj-setup'))->assertCreated();

    $balance = InventoryBalance::query()->where([
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
    ])->firstOrFail();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/adjustments', [
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'quantity_delta' => -2,
        'reason_code' => InventoryReasonCode::Damaged->value,
        'expected_version' => 999,
    ], InventoryFixtures::idempotencyHeader('adj-stale'))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'STALE_INVENTORY_VERSION');
});

it('requires idempotency key on stock-changing posts', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $warehouse = InventoryFixtures::defaultWarehouse();
    $variant = InventoryFixtures::activeVariant();

    $this->actingAs($manager, 'web')->postJson('/api/v1/admin/inventory/receipts', [
        'warehouse_id' => $warehouse->id,
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        'reason_code' => InventoryReasonCode::SupplierReceipt->value,
    ])->assertUnprocessable();
});
