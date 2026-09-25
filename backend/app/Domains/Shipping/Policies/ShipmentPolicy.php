<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Shipping\Models\Shipment;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class ShipmentPolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::FulfillmentView->value)
            || $actor->can(Permission::OrdersView->value);
    }

    public function view(Authorizable $actor, Shipment $shipment): bool
    {
        return $this->viewAny($actor);
    }

    public function manage(Authorizable $actor): bool
    {
        return $actor->can(Permission::FulfillmentManage->value)
            || $actor->can(Permission::OrdersManage->value);
    }
}
