<?php

declare(strict_types=1);

namespace App\Domains\Legal\Policies;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Models\LegalRule;

final class LegalPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::LegalRulesView->value)
            || $actor->can(Permission::LegalSourcesView->value);
    }

    public function view(User $actor, mixed $record = null): bool
    {
        return $this->viewAny($actor);
    }

    public function manageSources(User $actor): bool
    {
        return $actor->can(Permission::LegalSourcesManage->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function verifySources(User $actor): bool
    {
        return $actor->can(Permission::LegalSourcesVerify->value);
    }

    public function manageDocuments(User $actor): bool
    {
        return $actor->can(Permission::LegalDocumentsManage->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function uploadVersions(User $actor): bool
    {
        return $actor->can(Permission::LegalVersionsUpload->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function reviewVersions(User $actor): bool
    {
        return $actor->can(Permission::LegalVersionsReview->value)
            || $actor->can(Permission::LegalRulesPublish->value);
    }

    public function manageProvisions(User $actor): bool
    {
        return $actor->can(Permission::LegalProvisionsManage->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function createRules(User $actor): bool
    {
        return $actor->can(Permission::LegalRulesCreate->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function updateRules(User $actor): bool
    {
        return $actor->can(Permission::LegalRulesUpdate->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function reviewRules(User $actor): bool
    {
        return $actor->can(Permission::LegalRulesReview->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function publish(User $actor, ?LegalRule $rule = null): bool
    {
        return $actor->can(Permission::LegalRulesPublishAction->value)
            || $actor->can(Permission::LegalRulesPublish->value);
    }

    public function supersede(User $actor): bool
    {
        return $actor->can(Permission::LegalRulesSupersede->value)
            || $actor->can(Permission::LegalRulesPublish->value);
    }

    public function viewConflicts(User $actor): bool
    {
        return $actor->can(Permission::LegalConflictsView->value)
            || $this->viewAny($actor);
    }

    public function resolveConflicts(User $actor): bool
    {
        return $actor->can(Permission::LegalConflictsResolve->value)
            || $actor->can(Permission::LegalRulesPublish->value);
    }

    public function viewSeasons(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonsView->value) || $this->viewAny($actor);
    }

    public function createSeasons(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonsCreate->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function updateSeasons(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonsUpdate->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function reviewSeasons(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonsReview->value)
            || $actor->can(Permission::LegalRulesManage->value);
    }

    public function publishSeasons(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonsPublish->value)
            || $actor->can(Permission::LegalRulesPublish->value);
    }

    public function supersedeSeasons(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonsSupersede->value)
            || $actor->can(Permission::LegalRulesSupersede->value)
            || $actor->can(Permission::LegalRulesPublish->value);
    }

    public function generateSeasons(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonsGenerate->value)
            || $actor->can(Permission::LegalRulesPublish->value);
    }

    public function viewOverrides(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonOverridesView->value) || $this->viewSeasons($actor);
    }

    public function createOverrides(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonOverridesCreate->value)
            || $actor->can(Permission::LegalSeasonsCreate->value);
    }

    public function reviewOverrides(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonOverridesReview->value)
            || $this->reviewSeasons($actor);
    }

    public function publishOverrides(User $actor): bool
    {
        return $actor->can(Permission::LegalSeasonOverridesPublish->value)
            || $this->publishSeasons($actor);
    }

    public function previewCalendar(User $actor): bool
    {
        return $actor->can(Permission::LegalCalendarPreview->value) || $this->viewSeasons($actor);
    }

    public function viewCoverage(User $actor): bool
    {
        return $actor->can(Permission::LegalCalendarCoverage->value) || $this->viewSeasons($actor);
    }

    public function viewGenerationRuns(User $actor): bool
    {
        return $actor->can(Permission::LegalCalendarGenerationRunsView->value) || $this->viewSeasons($actor);
    }
}
