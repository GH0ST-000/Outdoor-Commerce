<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Policies;

use App\Domains\Catalog\Models\Product;
use App\Domains\Identity\Enums\Permission;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class ProductPolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::CatalogView->value);
    }

    public function view(Authorizable $actor, Product $product): bool
    {
        return $actor->can(Permission::CatalogView->value);
    }

    public function create(Authorizable $actor): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function update(Authorizable $actor, Product $product): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function publish(Authorizable $actor, Product $product): bool
    {
        return $actor->can(Permission::CatalogPublish->value);
    }

    public function archive(Authorizable $actor, Product $product): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function restore(Authorizable $actor, Product $product): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }
}
