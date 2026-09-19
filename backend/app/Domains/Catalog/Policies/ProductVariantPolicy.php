<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Policies;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Enums\Permission;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class ProductVariantPolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::CatalogView->value);
    }

    public function view(Authorizable $actor, ProductVariant $variant): bool
    {
        return $actor->can(Permission::CatalogView->value);
    }

    public function create(Authorizable $actor): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function update(Authorizable $actor, ProductVariant $variant): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function publish(Authorizable $actor, ProductVariant $variant): bool
    {
        return $actor->can(Permission::CatalogPublish->value);
    }

    public function archive(Authorizable $actor, ProductVariant $variant): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function restore(Authorizable $actor, ProductVariant $variant): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }
}
