<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

it('lists users with pagination search filters and safe fields', function (): void {
    $admin = $this->createAdmin(['email' => 'ops@example.test']);
    User::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Hunter',
        'email' => 'ana.hunter@example.test',
        'status' => UserStatus::Active,
    ]);
    User::factory()->disabled()->create(['email' => 'gone@example.test']);

    $response = $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/users?search=ana&status=active&sort=email&direction=asc&per_page=10');

    $response
        ->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonMissingPath('data.0.password')
        ->assertJsonMissingPath('data.0.remember_token')
        ->assertJsonMissingPath('data.0.addresses');

    expect(collect($response->json('data'))->pluck('email'))->toContain('ana.hunter@example.test');
});

it('rejects unknown sorts and caps per_page', function (): void {
    $admin = $this->createAdmin();

    $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/users?sort=password')
        ->assertUnprocessable();

    $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/users?per_page=999')
        ->assertUnprocessable();
});

it('filters by role', function (): void {
    $admin = $this->createAdmin(['email' => 'admin-filter@example.test']);
    $this->createUserWithRole(Role::CatalogManager, ['email' => 'catalog@example.test']);

    $response = $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/users?role=catalog-manager');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('email')->all())->toContain('catalog@example.test');
});

it('changes user status and audits the change', function (): void {
    $admin = $this->createAdmin();
    $target = User::factory()->create();

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/users/{$target->id}/status", ['status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled');

    expect($target->fresh()?->status)->toBe(UserStatus::Disabled);
    expect(AuditLog::query()->where('event', AuditEvent::UserStatusChanged->value)->exists())->toBeTrue();
});

it('blocks disabling the last active administrator', function (): void {
    $admin = $this->createAdmin();

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/users/{$admin->id}/status", ['status' => 'disabled'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'LAST_ACTIVE_ADMIN_REQUIRED');

    expect($admin->fresh()?->status)->toBe(UserStatus::Active);
});

it('allows disabling one admin when another remains', function (): void {
    $adminA = $this->createAdmin(['email' => 'a@example.test']);
    $adminB = $this->createAdmin(['email' => 'b@example.test']);

    $this->actingAs($adminA, 'web')
        ->patchJson("/api/v1/admin/users/{$adminB->id}/status", ['status' => 'disabled'])
        ->assertOk();

    expect($adminB->fresh()?->status)->toBe(UserStatus::Disabled);
});

it('stops protected access for a disabled session', function (): void {
    $admin = $this->createAdmin();
    $target = User::factory()->create();

    $this->actingAs($target, 'web')->getJson('/api/v1/auth/me')->assertOk();

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/users/{$target->id}/status", ['status' => 'disabled'])
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->actingAs($target->fresh(), 'web')
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('assigns and removes approved roles with audit and last-admin protection', function (): void {
    $admin = $this->createAdmin(['email' => 'role-admin@example.test']);
    $secondAdmin = $this->createAdmin(['email' => 'second@example.test']);
    $target = User::factory()->create();

    $this->actingAs($admin, 'web')
        ->putJson("/api/v1/admin/users/{$target->id}/roles", [
            'roles' => [Role::CatalogManager->value],
        ])
        ->assertOk()
        ->assertJsonPath('data.roles.0', Role::CatalogManager->value);

    expect(AuditLog::query()->where('event', AuditEvent::UserRolesChanged->value)->exists())->toBeTrue();

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->putJson("/api/v1/admin/users/{$admin->id}/roles", ['roles' => []])
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->actingAs($secondAdmin, 'web')
        ->putJson("/api/v1/admin/users/{$secondAdmin->id}/roles", ['roles' => []])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'LAST_ACTIVE_ADMIN_REQUIRED');
});

it('rejects unknown roles and registration role injection', function (): void {
    $admin = $this->createAdmin();
    $target = User::factory()->create();

    $this->actingAs($admin, 'web')
        ->putJson("/api/v1/admin/users/{$target->id}/roles", [
            'roles' => ['superuser'],
        ])
        ->assertUnprocessable();

    $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Role',
        'last_name' => 'Inject',
        'email' => 'inject@example.test',
        'password' => 'SecurePass12',
        'password_confirmation' => 'SecurePass12',
        'role' => 'admin',
        'roles' => ['admin'],
    ])->assertCreated();

    $created = User::query()->where('email', 'inject@example.test')->first();
    expect($created?->getRoleNames()->all())->toBe([]);
});

it('keeps user list query count controlled', function (): void {
    $admin = $this->createAdmin();
    User::factory()->count(5)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/users')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(15);
});
