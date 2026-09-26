<?php

declare(strict_types=1);

namespace App\Domains\Geography\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Identity\Models\User;

final class SpatialPolicy
{
    public function viewSources(User $actor): bool
    {
        return $actor->can(Permission::SpatialSourcesView->value);
    }

    public function manageSources(User $actor): bool
    {
        return $actor->can(Permission::SpatialSourcesManage->value);
    }

    public function verifySources(User $actor): bool
    {
        return $actor->can(Permission::SpatialSourcesVerify->value);
    }

    public function viewDatasets(User $actor): bool
    {
        return $actor->can(Permission::SpatialDatasetsView->value) || $this->viewSources($actor);
    }

    public function manageDatasets(User $actor): bool
    {
        return $actor->can(Permission::SpatialDatasetsManage->value);
    }

    public function uploadVersions(User $actor): bool
    {
        return $actor->can(Permission::SpatialVersionsUpload->value);
    }

    public function validateVersions(User $actor): bool
    {
        return $actor->can(Permission::SpatialVersionsValidate->value);
    }

    public function importVersions(User $actor): bool
    {
        return $actor->can(Permission::SpatialVersionsImport->value);
    }

    public function reviewVersions(User $actor): bool
    {
        return $actor->can(Permission::SpatialVersionsReview->value)
            || $actor->can(Permission::SpatialGeometryReview->value);
    }

    public function publishVersions(User $actor): bool
    {
        return $actor->can(Permission::SpatialVersionsPublish->value)
            || $actor->can(Permission::SpatialGeometryPublish->value);
    }

    public function viewZones(User $actor): bool
    {
        return $actor->can(Permission::SpatialZonesView->value) || $this->viewDatasets($actor);
    }

    public function manageZones(User $actor): bool
    {
        return $actor->can(Permission::SpatialZonesManage->value);
    }

    public function assignRules(User $actor): bool
    {
        return $actor->can(Permission::SpatialRulesAssign->value);
    }

    public function viewConflicts(User $actor): bool
    {
        return $actor->can(Permission::SpatialConflictsView->value);
    }

    public function resolveConflicts(User $actor): bool
    {
        return $actor->can(Permission::SpatialConflictsResolve->value);
    }

    public function previewEvaluate(User $actor): bool
    {
        return $actor->can(Permission::SpatialPreviewEvaluate->value) || $this->viewZones($actor);
    }

    public function viewAudit(User $actor): bool
    {
        return $actor->can(Permission::SpatialAuditView->value);
    }
}
