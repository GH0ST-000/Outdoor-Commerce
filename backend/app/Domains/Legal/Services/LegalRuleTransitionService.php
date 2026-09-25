<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalReviewDecision;
use App\Domains\Legal\Enums\LegalReviewType;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Models\LegalReview;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;

final class LegalRuleTransitionService
{
    public function __construct(
        private readonly LegalRuleStateMachine $states,
        private readonly LegalPublicationValidator $publishing,
        private readonly LegalPublicCache $cache,
        private readonly LegalAuditRecorder $audit,
    ) {}

    public function submitReview(LegalRule $rule, User $actor): LegalRule
    {
        $this->states->assertTransition($rule->status, LegalRuleStatus::InReview);
        $rule->status = LegalRuleStatus::InReview;
        $rule->updated_by = $actor->id;
        $rule->save();
        $this->review($rule, $actor, LegalReviewType::RuleContentReview, LegalReviewDecision::ChangesRequested, 'Submitted for review');
        $this->audit->record(AuditEvent::LegalRuleSubmittedReview, $actor, 'legal_rule', $rule->public_id);
        $this->cache->bump();

        return $rule;
    }

    public function approve(LegalRule $rule, User $actor): LegalRule
    {
        $this->states->assertTransition($rule->status, LegalRuleStatus::Approved);
        $rule->status = LegalRuleStatus::Approved;
        $rule->reviewed_by = $actor->id;
        $rule->reviewed_at = now();
        $rule->verification_level = LegalVerificationLevel::ProvisionVerified;
        $rule->updated_by = $actor->id;
        $rule->save();
        $this->review($rule, $actor, LegalReviewType::LegalReview, LegalReviewDecision::Approved, 'Approved');
        $this->audit->record(AuditEvent::LegalRuleApproved, $actor, 'legal_rule', $rule->public_id);
        $this->cache->bump();

        return $rule;
    }

    public function publish(LegalRule $rule, User $actor): LegalRule
    {
        return DB::transaction(function () use ($rule, $actor): LegalRule {
            $locked = LegalRule::query()->lockForUpdate()->findOrFail($rule->id);
            $this->states->assertTransition($locked->status, LegalRuleStatus::Published);
            $locked->load(['citations.provision.version.document.source', 'limits', 'conditions']);
            $this->publishing->assertCanPublish($locked, $actor);
            $locked->status = LegalRuleStatus::Published;
            $locked->published_by = $actor->id;
            $locked->published_at = now();
            $locked->verification_level = LegalVerificationLevel::LegallyReviewed;
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->review($locked, $actor, LegalReviewType::PublicationReview, LegalReviewDecision::Approved, 'Published');
            $this->audit->record(AuditEvent::LegalRulePublished, $actor, 'legal_rule', $locked->public_id);
            $this->cache->bump();

            return $locked;
        });
    }

    public function reject(LegalRule $rule, User $actor, string $reason): LegalRule
    {
        $this->states->assertTransition($rule->status, LegalRuleStatus::Rejected);
        $rule->status = LegalRuleStatus::Rejected;
        $rule->reviewed_by = $actor->id;
        $rule->reviewed_at = now();
        $rule->updated_by = $actor->id;
        $rule->save();
        $this->review($rule, $actor, LegalReviewType::LegalReview, LegalReviewDecision::Rejected, $reason);
        $this->audit->record(AuditEvent::LegalRuleRejected, $actor, 'legal_rule', $rule->public_id, null, ['reason' => $reason]);
        $this->cache->bump();

        return $rule;
    }

    public function supersede(LegalRule $rule, User $actor, string $reason): LegalRule
    {
        $this->states->assertTransition($rule->status, LegalRuleStatus::Superseded);
        $rule->status = LegalRuleStatus::Superseded;
        $rule->updated_by = $actor->id;
        $rule->save();
        $this->audit->record(AuditEvent::LegalRuleSuperseded, $actor, 'legal_rule', $rule->public_id, null, ['reason' => $reason]);
        $this->cache->bump();

        return $rule;
    }

    private function review(LegalRule $rule, User $actor, LegalReviewType $type, LegalReviewDecision $decision, string $comments): void
    {
        LegalReview::query()->create([
            'reviewable_type' => 'legal_rule',
            'reviewable_id' => $rule->id,
            'review_type' => $type,
            'decision' => $decision,
            'comments' => $comments,
            'reviewer_id' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }
}
