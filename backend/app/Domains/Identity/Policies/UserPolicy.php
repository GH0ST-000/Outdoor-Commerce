<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Identity\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    public function manageStatus(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersStatusManage->value);
    }

    public function manageRoles(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersRolesManage->value);
    }
}
