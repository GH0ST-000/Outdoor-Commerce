<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalReviewDecision;
use App\Domains\Legal\Enums\LegalReviewType;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalReview;
use App\Domains\Legal\Models\LegalSeasonOverride;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;

final class SeasonOverrideTransitionService
{
    public function __construct(
        private readonly LegalRuleStateMachine $states,
        private readonly LegalCitationIntegrityValidator $citations,
        private readonly SeasonConflictDetector $conflicts,
        private readonly SeasonProjectionService $projections,
        private readonly LegalAuditRecorder $audit,
        private readonly LegalPublicCache $cache,
    ) {}

    public function submitReview(LegalSeasonOverride $override, User $actor): LegalSeasonOverride
    {
        $this->states->assertTransition($override->status, LegalRuleStatus::InReview);
        $override->status = LegalRuleStatus::InReview;
        $override->updated_by = $actor->id;
        $override->save();
        $this->review($override, $actor, LegalReviewDecision::ChangesRequested, 'Submitted for review');
        $this->audit->record(AuditEvent::LegalSeasonOverrideSubmittedReview, $actor, 'legal_season_override', $override->public_id);
        $this->cache->bump();

        return $override;
    }

    public function approve(LegalSeasonOverride $override, User $actor): LegalSeasonOverride
    {
        $this->states->assertTransition($override->status, LegalRuleStatus::Approved);
        $override->status = LegalRuleStatus::Approved;
        $override->reviewed_by = $actor->id;
        $override->reviewed_at = now();
        $override->verification_level = LegalVerificationLevel::ProvisionVerified;
        $override->updated_by = $actor->id;
        $override->save();
        $this->review($override, $actor, LegalReviewDecision::Approved, 'Approved');
        $this->audit->record(AuditEvent::LegalSeasonOverrideApproved, $actor, 'legal_season_override', $override->public_id);
        $this->cache->bump();

        return $override;
    }

    public function publish(LegalSeasonOverride $override, User $actor): LegalSeasonOverride
    {
        return DB::transaction(function () use ($override, $actor): LegalSeasonOverride {
            $locked = LegalSeasonOverride::query()->lockForUpdate()->findOrFail($override->id);
            $this->states->assertTransition($locked->status, LegalRuleStatus::Published);
            $locked->loadMissing('rule.citations.provision.version.document.source');
            if ($locked->rule === null || $locked->rule->status !== LegalRuleStatus::Published) {
                throw LegalException::publicationInvalid(['override' => 'A published override requires a published legal rule.']);
            }
            $this->citations->assertPublishable($locked->rule);
            $this->conflicts->assertOverridePublishable($locked);
            $locked->status = LegalRuleStatus::Published;
            $locked->published_by = $actor->id;
            $locked->published_at = now();
            $locked->verification_level = LegalVerificationLevel::LegallyReviewed;
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->review($locked, $actor, LegalReviewDecision::Approved, 'Published');
            $this->audit->record(AuditEvent::LegalSeasonOverridePublished, $actor, 'legal_season_override', $locked->public_id);
            if ($locked->definition !== null) {
                $this->projections->generateDefinition($locked->definition);
            }
            $this->cache->bump();

            return $locked;
        });
    }

    public function reject(LegalSeasonOverride $override, User $actor, string $reason): LegalSeasonOverride
    {
        $this->states->assertTransition($override->status, LegalRuleStatus::Rejected);
        $override->status = LegalRuleStatus::Rejected;
        $override->reviewed_by = $actor->id;
        $override->reviewed_at = now();
        $override->updated_by = $actor->id;
        $override->save();
        $this->review($override, $actor, LegalReviewDecision::Rejected, $reason);
        $this->audit->record(AuditEvent::LegalSeasonOverrideRejected, $actor, 'legal_season_override', $override->public_id, null, ['reason' => $reason]);
        $this->cache->bump();

        return $override;
    }

    private function review(LegalSeasonOverride $override, User $actor, LegalReviewDecision $decision, string $comments): void
    {
        LegalReview::query()->create([
            'reviewable_type' => 'legal_season_override',
            'reviewable_id' => $override->id,
            'review_type' => LegalReviewType::PublicationReview,
            'decision' => $decision,
            'comments' => $comments,
            'reviewer_id' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }
}
