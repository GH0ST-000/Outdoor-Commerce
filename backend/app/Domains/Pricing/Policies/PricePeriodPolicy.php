<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Pricing\Models\PricePeriod;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class PricePeriodPolicy
{
    public function view(Authorizable $actor, PricePeriod $period): bool
    {
        return $actor->can(Permission::PricingView->value);
    }

    public function update(Authorizable $actor, PricePeriod $period): bool
    {
        return $actor->can(Permission::PricingManage->value);
    }

    public function manage(Authorizable $actor, PricePeriod $period): bool
    {
        return $this->update($actor, $period);
    }

    public function publish(Authorizable $actor, PricePeriod $period): bool
    {
        return $actor->can(Permission::PricingPublish->value);
    }
}
