<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class InventoryReservationPolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::InventoryView->value);
    }

    public function view(Authorizable $actor, InventoryReservation $reservation): bool
    {
        return $actor->can(Permission::InventoryView->value);
    }

    public function manage(Authorizable $actor, InventoryReservation $reservation): bool
    {
        return $actor->can(Permission::InventoryReservationsManage->value);
    }
}
