<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Exceptions\LastActiveAdminException;
use App\Domains\Identity\Models\User;

final class LastActiveAdminGuard
{
    /**
     * Ensure disabling or demoting `$user` would not remove the last active admin.
     *
     * @throws LastActiveAdminException
     */
    public function assertCanLoseAdminAccess(User $user): void
    {
        if (! $user->hasRole(Role::Admin->value)) {
            return;
        }

        if ($user->status !== UserStatus::Active) {
            return;
        }

        $otherActiveAdminExists = User::query()
            ->whereKeyNot($user->id)
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', function ($query): void {
                $query->where('name', Role::Admin->value)
                    ->where('guard_name', 'web');
            })
            ->lockForUpdate()
            ->exists();

        if (! $otherActiveAdminExists) {
            throw LastActiveAdminException::cannotRemoveRole();
        }
    }
}
