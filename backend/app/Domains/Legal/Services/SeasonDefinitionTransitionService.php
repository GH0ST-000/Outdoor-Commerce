<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalReviewDecision;
use App\Domains\Legal\Enums\LegalReviewType;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Models\LegalReview;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;

final class SeasonDefinitionTransitionService
{
    public function __construct(
        private readonly LegalRuleStateMachine $states,
        private readonly SeasonDefinitionValidator $validator,
        private readonly SeasonConflictDetector $conflicts,
        private readonly SeasonProjectionService $projections,
        private readonly LegalAuditRecorder $audit,
        private readonly LegalPublicCache $cache,
    ) {}

    public function submitReview(LegalSeasonDefinition $definition, User $actor): LegalSeasonDefinition
    {
        $this->states->assertTransition($definition->status, LegalRuleStatus::InReview);
        $definition->status = LegalRuleStatus::InReview;
        $definition->updated_by = $actor->id;
        $definition->save();
        $this->review($definition, $actor, LegalReviewType::RuleContentReview, LegalReviewDecision::ChangesRequested, 'Submitted for review');
        $this->audit->record(AuditEvent::LegalSeasonSubmittedReview, $actor, 'legal_season_definition', $definition->public_id);
        $this->cache->bump();

        return $definition;
    }

    public function approve(LegalSeasonDefinition $definition, User $actor): LegalSeasonDefinition
    {
        $this->states->assertTransition($definition->status, LegalRuleStatus::Approved);
        $definition->status = LegalRuleStatus::Approved;
        $definition->reviewed_by = $actor->id;
        $definition->reviewed_at = now();
        $definition->verification_level = LegalVerificationLevel::ProvisionVerified;
        $definition->updated_by = $actor->id;
        $definition->save();
        $this->review($definition, $actor, LegalReviewType::LegalReview, LegalReviewDecision::Approved, 'Approved');
        $this->audit->record(AuditEvent::LegalSeasonApproved, $actor, 'legal_season_definition', $definition->public_id);
        $this->cache->bump();

        return $definition;
    }

    public function publish(LegalSeasonDefinition $definition, User $actor): LegalSeasonDefinition
    {
        return DB::transaction(function () use ($definition, $actor): LegalSeasonDefinition {
            $locked = LegalSeasonDefinition::query()->lockForUpdate()->findOrFail($definition->id);
            $this->states->assertTransition($locked->status, LegalRuleStatus::Published);
            $this->validator->assertPublishable($locked);
            $this->conflicts->assertPublishable($locked);
            $locked->status = LegalRuleStatus::Published;
            $locked->published_by = $actor->id;
            $locked->published_at = now();
            $locked->verification_level = LegalVerificationLevel::LegallyReviewed;
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->review($locked, $actor, LegalReviewType::PublicationReview, LegalReviewDecision::Approved, 'Published');
            $this->audit->record(AuditEvent::LegalSeasonPublished, $actor, 'legal_season_definition', $locked->public_id);
            $this->projections->generateDefinition($locked);
            $this->conflicts->detectForDefinition($locked);
            $this->cache->bump();

            return $locked;
        });
    }

    public function reject(LegalSeasonDefinition $definition, User $actor, string $reason): LegalSeasonDefinition
    {
        $this->states->assertTransition($definition->status, LegalRuleStatus::Rejected);
        $definition->status = LegalRuleStatus::Rejected;
        $definition->reviewed_by = $actor->id;
        $definition->reviewed_at = now();
        $definition->updated_by = $actor->id;
        $definition->save();
        $this->review($definition, $actor, LegalReviewType::LegalReview, LegalReviewDecision::Rejected, $reason);
        $this->audit->record(AuditEvent::LegalSeasonRejected, $actor, 'legal_season_definition', $definition->public_id, null, ['reason' => $reason]);
        $this->cache->bump();

        return $definition;
    }

    public function supersede(LegalSeasonDefinition $definition, User $actor, string $reason): LegalSeasonDefinition
    {
        $this->states->assertTransition($definition->status, LegalRuleStatus::Superseded);
        $definition->status = LegalRuleStatus::Superseded;
        $definition->updated_by = $actor->id;
        $definition->save();
        $this->projections->invalidateForDefinition($definition);
        $this->audit->record(AuditEvent::LegalSeasonSuperseded, $actor, 'legal_season_definition', $definition->public_id, null, ['reason' => $reason]);
        $this->cache->bump();

        return $definition;
    }

    private function review(LegalSeasonDefinition $definition, User $actor, LegalReviewType $type, LegalReviewDecision $decision, string $comments): void
    {
        LegalReview::query()->create([
            'reviewable_type' => 'legal_season_definition',
            'reviewable_id' => $definition->id,
            'review_type' => $type,
            'decision' => $decision,
            'comments' => $comments,
            'reviewer_id' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }
}
