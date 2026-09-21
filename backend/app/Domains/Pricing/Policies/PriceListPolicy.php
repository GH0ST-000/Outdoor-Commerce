<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Pricing\Models\PriceList;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class PriceListPolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::PricingView->value);
    }

    public function view(Authorizable $actor, PriceList $priceList): bool
    {
        return $actor->can(Permission::PricingView->value);
    }

    public function create(Authorizable $actor): bool
    {
        return $actor->can(Permission::PricingManage->value);
    }

    public function update(Authorizable $actor, PriceList $priceList): bool
    {
        return $actor->can(Permission::PricingManage->value);
    }

    public function publish(Authorizable $actor, PriceList $priceList): bool
    {
        return $actor->can(Permission::PricingPublish->value);
    }

    public function archive(Authorizable $actor, PriceList $priceList): bool
    {
        return $actor->can(Permission::PricingPublish->value);
    }

    public function restore(Authorizable $actor, PriceList $priceList): bool
    {
        return $actor->can(Permission::PricingPublish->value);
    }
}
