<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Exceptions\InvalidRoleException;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\LastActiveAdminGuard;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class AssignUserRolesAction
{
    public function __construct(
        private readonly LastActiveAdminGuard $lastActiveAdminGuard,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @param  list<string>  $roleNames
     */
    public function execute(
        User $actor,
        User $target,
        array $roleNames,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): User {
        $normalized = [];

        foreach ($roleNames as $roleName) {
            $role = Role::tryFrom($roleName);

            if ($role === null) {
                throw InvalidRoleException::unknown($roleName);
            }

            $normalized[] = $role->value;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return DB::transaction(function () use (
            $actor,
            $target,
            $normalized,
            $requestId,
            $ipAddress,
            $userAgent,
        ): User {
            /** @var User $locked */
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $oldRoles = $locked->getRoleNames()->sort()->values()->all();

            $losingAdmin = in_array(Role::Admin->value, $oldRoles, true)
                && ! in_array(Role::Admin->value, $normalized, true);

            if ($losingAdmin) {
                $this->lastActiveAdminGuard->assertCanLoseAdminAccess($locked);
            }

            if ($oldRoles === $normalized) {
                return $locked;
            }

            $locked->syncRoles($normalized);
            $this->permissionRegistrar->forgetCachedPermissions();

            $newRoles = $locked->getRoleNames()->sort()->values()->all();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::UserRolesChanged,
                actorUserId: $actor->id,
                subjectType: 'user',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['roles' => $oldRoles],
                newValues: ['roles' => $newRoles],
            ));

            return $locked->fresh(['roles']) ?? $locked;
        });
    }
}
