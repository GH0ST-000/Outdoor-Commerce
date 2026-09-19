<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Support\RolePermissionMatrix;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSynchronizer
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     permissions_created: list<string>,
     *     roles_created: list<string>,
     *     role_permissions_synced: array<string, list<string>>,
     *     unknown_roles_preserved: list<string>
     * }
     */
    public function synchronize(bool $dryRun = false): array
    {
        $permissionsCreated = [];
        $rolesCreated = [];
        $rolePermissionsSynced = [];
        $unknownRolesPreserved = [];

        $desiredPermissionNames = array_map(
            static fn (Permission $permission): string => $permission->value,
            Permission::all(),
        );

        $run = function () use (
            $dryRun,
            $desiredPermissionNames,
            &$permissionsCreated,
            &$rolesCreated,
            &$rolePermissionsSynced,
            &$unknownRolesPreserved,
        ): void {
            foreach ($desiredPermissionNames as $name) {
                $exists = SpatiePermission::query()
                    ->where('name', $name)
                    ->where('guard_name', 'web')
                    ->exists();

                if (! $exists) {
                    $permissionsCreated[] = $name;

                    if (! $dryRun) {
                        SpatiePermission::findOrCreate($name, 'web');
                    }
                }
            }

            $knownRoleNames = array_map(
                static fn (Role $role): string => $role->value,
                Role::cases(),
            );

            $existingRoles = SpatieRole::query()
                ->where('guard_name', 'web')
                ->pluck('name')
                ->all();

            foreach ($existingRoles as $existingRole) {
                if (! in_array($existingRole, $knownRoleNames, true)) {
                    $unknownRolesPreserved[] = $existingRole;
                }
            }

            foreach (Role::cases() as $role) {
                $roleExists = SpatieRole::query()
                    ->where('name', $role->value)
                    ->where('guard_name', 'web')
                    ->exists();

                if (! $roleExists) {
                    $rolesCreated[] = $role->value;

                    if (! $dryRun) {
                        SpatieRole::findOrCreate($role->value, 'web');
                    }
                }

                $permissionNames = array_map(
                    static fn (Permission $permission): string => $permission->value,
                    RolePermissionMatrix::permissionsFor($role),
                );
                sort($permissionNames);
                $rolePermissionsSynced[$role->value] = $permissionNames;

                if (! $dryRun) {
                    /** @var SpatieRole $spatieRole */
                    $spatieRole = SpatieRole::findByName($role->value, 'web');
                    $spatieRole->syncPermissions($permissionNames);
                }
            }

            if (! $dryRun) {
                $this->registrar->forgetCachedPermissions();
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        sort($permissionsCreated);
        sort($rolesCreated);
        sort($unknownRolesPreserved);

        return [
            'dry_run' => $dryRun,
            'permissions_created' => $permissionsCreated,
            'roles_created' => $rolesCreated,
            'role_permissions_synced' => $rolePermissionsSynced,
            'unknown_roles_preserved' => $unknownRolesPreserved,
        ];
    }
}
