<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

it('lets admin access day-5 admin endpoints', function (): void {
    $admin = $this->createAdmin();

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/context')->assertOk();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/users')->assertOk();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/roles')->assertOk();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/audit-logs')->assertOk();
});

it('lets catalog manager open the admin shell but not manage users or audit logs', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);

    $this->actingAs($manager, 'web')->getJson('/api/v1/admin/context')->assertOk();
    $this->actingAs($manager, 'web')->getJson('/api/v1/admin/users')->assertForbidden();
    $this->actingAs($manager, 'web')->getJson('/api/v1/admin/audit-logs')->assertForbidden();
    $this->actingAs($manager, 'web')->getJson('/api/v1/admin/roles')->assertForbidden();
});

it('lets order manager open the admin shell but not manage users', function (): void {
    $manager = $this->createUserWithRole(Role::OrderManager);
    $target = User::factory()->create();

    $this->actingAs($manager, 'web')->getJson('/api/v1/admin/context')->assertOk();
    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/users/{$target->id}/status", ['status' => 'disabled'])
        ->assertForbidden();
});

it('lets legal editor open the admin shell but not manage user status', function (): void {
    $editor = $this->createUserWithRole(Role::LegalEditor);
    $target = User::factory()->create();

    $this->actingAs($editor, 'web')->getJson('/api/v1/admin/context')->assertOk();
    $this->actingAs($editor, 'web')
        ->patchJson("/api/v1/admin/users/{$target->id}/status", ['status' => 'disabled'])
        ->assertForbidden();
});

it('rejects ordinary customers and guests', function (): void {
    $customer = User::factory()->create();

    $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    $this->actingAs($customer, 'web')->getJson('/api/v1/admin/users')->assertForbidden();
});
