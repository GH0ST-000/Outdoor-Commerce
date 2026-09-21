<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Policies;

use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Identity\Enums\Permission;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Media rides on catalog permissions: viewing needs `catalog.view`, and every
 * mutation — including reordering and choosing the primary image — needs
 * `catalog.manage`. Publishing is deliberately not involved; attaching an image
 * never changes what is visible to customers on its own.
 */
final class MediaAttachmentPolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::CatalogView->value);
    }

    public function view(Authorizable $actor, MediaAttachment $attachment): bool
    {
        return $actor->can(Permission::CatalogView->value);
    }

    public function create(Authorizable $actor): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function update(Authorizable $actor, MediaAttachment $attachment): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function delete(Authorizable $actor, MediaAttachment $attachment): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function reorder(Authorizable $actor): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }

    public function retry(Authorizable $actor, MediaAttachment $attachment): bool
    {
        return $actor->can(Permission::CatalogManage->value);
    }
}
