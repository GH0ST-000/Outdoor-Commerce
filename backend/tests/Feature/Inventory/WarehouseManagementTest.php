<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\InventoryFixtures;

uses(InteractsWithAccessControl::class);

it('creates warehouse and sets first active as default', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);

    $this->actingAs($manager, 'web')
        ->postJson('/api/v1/admin/warehouses', [
            'code' => 'tbs-01',
            'name' => 'Tbilisi Main',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'TBS-01')
        ->assertJsonPath('data.is_default', true);

    expect(Warehouse::query()->count())->toBe(1);
    expect(AuditLog::query()->where('event', AuditEvent::WarehouseCreated->value)->exists())->toBeTrue();
});

it('lists warehouses with default pagination of 10', function (): void {
    $viewer = $this->createUserWithRole(Role::CatalogManager);
    Warehouse::factory()->count(11)->create();

    $response = $this->actingAs($viewer, 'web')->getJson('/api/v1/admin/warehouses');
    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(10);
    expect($response->json('data'))->toHaveCount(10);
});

it('denies catalog manager from creating warehouses', function (): void {
    $catalog = $this->createUserWithRole(Role::CatalogManager);

    $this->actingAs($catalog, 'web')
        ->postJson('/api/v1/admin/warehouses', ['code' => 'X', 'name' => 'X'])
        ->assertForbidden();
});

it('switches default warehouse transactionally', function (): void {
    $manager = $this->createUserWithRole(Role::InventoryManager);
    $a = InventoryFixtures::defaultWarehouse(['code' => 'A']);
    $b = Warehouse::factory()->create(['code' => 'B']);

    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/warehouses/{$b->id}/default")
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect($a->fresh()->is_default)->toBeFalse();
    expect($b->fresh()->is_default)->toBeTrue();
});
