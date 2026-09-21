<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Permission;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Services\RolePermissionSynchronizer;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

it('creates roles and permissions idempotently', function (): void {
    $synchronizer = app(RolePermissionSynchronizer::class);

    $first = $synchronizer->synchronize();
    expect($first['permissions_created'])->not->toBeEmpty();
    expect($first['roles_created'])->toEqualCanonicalizing([
        Role::Admin->value,
        Role::CatalogManager->value,
        Role::InventoryManager->value,
        Role::PricingManager->value,
        Role::OrderManager->value,
        Role::LegalEditor->value,
    ]);

    $second = $synchronizer->synchronize();
    expect($second['permissions_created'])->toBeEmpty();
    expect($second['roles_created'])->toBeEmpty();

    expect(SpatiePermission::query()->count())->toBe(count(Permission::cases()));
    expect(SpatieRole::query()->where('guard_name', 'web')->count())->toBeGreaterThanOrEqual(6);

    $admin = SpatieRole::findByName(Role::Admin->value, 'web');
    expect($admin->permissions)->toHaveCount(count(Permission::cases()));
});

it('supports dry-run without writing', function (): void {
    $summary = app(RolePermissionSynchronizer::class)->synchronize(dryRun: true);

    expect($summary['dry_run'])->toBeTrue();
    expect(SpatiePermission::query()->count())->toBe(0);
    expect(SpatieRole::query()->count())->toBe(0);
});

it('preserves unknown production roles', function (): void {
    $this->syncAccessControl();

    SpatieRole::findOrCreate('custom-ops', 'web');

    $summary = app(RolePermissionSynchronizer::class)->synchronize();

    expect($summary['unknown_roles_preserved'])->toContain('custom-ops');
    expect(SpatieRole::findByName('custom-ops', 'web'))->not->toBeNull();
});

it('records a synchronization audit event via artisan', function (): void {
    $this->artisan('access-control:sync')
        ->assertSuccessful();

    expect(AuditLog::query()->where('event', AuditEvent::PermissionConfigurationSynchronized->value)->exists())->toBeTrue();
});
