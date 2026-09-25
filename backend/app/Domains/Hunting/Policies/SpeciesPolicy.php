<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Policies;

use App\Domains\Hunting\Models\Species;
use App\Domains\Identity\Enums\Permission;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class SpeciesPolicy
{
    public function viewAny(Authorizable $actor): bool
    {
        return $actor->can(Permission::SpeciesView->value);
    }

    public function view(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesView->value);
    }

    public function create(Authorizable $actor): bool
    {
        return $actor->can(Permission::SpeciesCreate->value);
    }

    public function update(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesUpdate->value);
    }

    public function delete(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesDelete->value);
    }

    public function review(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesReview->value);
    }

    public function publish(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesPublish->value);
    }

    public function archive(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesArchive->value);
    }

    public function manageTaxonomy(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesManageTaxonomy->value);
    }

    public function manageAliases(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesManageAliases->value);
    }

    public function manageSources(Authorizable $actor): bool
    {
        return $actor->can(Permission::SpeciesManageSources->value);
    }

    public function manageMedia(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesManageMedia->value);
    }

    public function viewRevisions(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesViewRevisions->value);
    }

    public function restoreRevision(Authorizable $actor, Species $species): bool
    {
        return $actor->can(Permission::SpeciesRestoreRevision->value);
    }
}
