<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Inventory\Models\InventoryBalance;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class InventoryBalancePolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::InventoryView->value);
    }

    public function view(Authorizable $actor, InventoryBalance $balance): bool
    {
        return $actor->can(Permission::InventoryView->value);
    }

    public function adjust(Authorizable $actor): bool
    {
        return $actor->can(Permission::InventoryAdjust->value);
    }

    public function transfer(Authorizable $actor): bool
    {
        return $actor->can(Permission::InventoryTransfer->value);
    }

    public function manageSettings(Authorizable $actor, InventoryBalance $balance): bool
    {
        return $actor->can(Permission::InventoryManage->value);
    }
}
