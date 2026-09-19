<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Permission;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

it('requires authentication for admin context', function (): void {
    $this->getJson('/api/v1/admin/context')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHORIZED');
});

it('rejects customers without admin access', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer, 'web')
        ->getJson('/api/v1/admin/context')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'ADMIN_ACCESS_REQUIRED');
});

it('rejects disabled administrators', function (): void {
    $admin = $this->createAdmin(['status' => UserStatus::Disabled]);

    $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/context')
        ->assertUnauthorized();
});

it('returns safe roles and permissions for each administrative role', function (Role $role): void {
    $user = $this->createUserWithRole($role);

    $response = $this->actingAs($user, 'web')
        ->getJson('/api/v1/admin/context');

    $response
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonMissingPath('data.user.password')
        ->assertHeader('X-Request-ID');

    $permissions = $response->json('data.permissions');
    expect($permissions)->toContain(Permission::AdminAccess->value);
    expect($permissions)->not->toContain('id');
    expect($response->json('data.roles'))->toContain($role->value);
})->with([
    Role::Admin,
    Role::CatalogManager,
    Role::OrderManager,
    Role::LegalEditor,
]);
