<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Pricing\Models\Promotion;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class PromotionPolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::PromotionsView->value);
    }

    public function view(Authorizable $actor, Promotion $promotion): bool
    {
        return $actor->can(Permission::PromotionsView->value);
    }

    public function create(Authorizable $actor): bool
    {
        return $actor->can(Permission::PromotionsManage->value);
    }

    public function update(Authorizable $actor, Promotion $promotion): bool
    {
        return $actor->can(Permission::PromotionsManage->value);
    }

    public function publish(Authorizable $actor, Promotion $promotion): bool
    {
        return $actor->can(Permission::PromotionsPublish->value);
    }

    public function archive(Authorizable $actor, Promotion $promotion): bool
    {
        return $actor->can(Permission::PromotionsPublish->value);
    }

    public function restore(Authorizable $actor, Promotion $promotion): bool
    {
        return $actor->can(Permission::PromotionsPublish->value);
    }
}
