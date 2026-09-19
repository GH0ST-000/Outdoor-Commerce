<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\RolePermissionSynchronizer;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithAccessControl
{
    protected function syncAccessControl(): void
    {
        app(RolePermissionSynchronizer::class)->synchronize();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function createUserWithRole(Role $role, array $attributes = []): User
    {
        $this->syncAccessControl();

        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh(['roles']) ?? $user;
    }

    protected function createAdmin(array $attributes = []): User
    {
        return $this->createUserWithRole(Role::Admin, $attributes);
    }
}
