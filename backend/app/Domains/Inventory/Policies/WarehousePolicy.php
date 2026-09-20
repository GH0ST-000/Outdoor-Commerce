<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class WarehousePolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::InventoryView->value);
    }

    public function view(Authorizable $actor, Warehouse $warehouse): bool
    {
        return $actor->can(Permission::InventoryView->value);
    }

    public function create(Authorizable $actor): bool
    {
        return $actor->can(Permission::InventoryManage->value);
    }

    public function update(Authorizable $actor, Warehouse $warehouse): bool
    {
        return $actor->can(Permission::InventoryManage->value);
    }

    public function archive(Authorizable $actor, Warehouse $warehouse): bool
    {
        return $actor->can(Permission::InventoryManage->value);
    }

    public function restore(Authorizable $actor, Warehouse $warehouse): bool
    {
        return $actor->can(Permission::InventoryManage->value);
    }
}
