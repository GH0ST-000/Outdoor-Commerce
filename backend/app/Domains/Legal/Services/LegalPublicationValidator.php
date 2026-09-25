<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleLimit;

final class LegalPublicationValidator
{
    public function __construct(
        private readonly LegalCitationIntegrityValidator $citations,
        private readonly LegalConflictDetector $conflicts,
    ) {}

    public function assertCanPublish(LegalRule $rule, User $actor): void
    {
        $issues = [];

        if (trim((string) $rule->interpretation_summary) === '') {
            $issues[] = 'interpretation_summary_required';
        }
        if ($rule->effective_until !== null && $rule->effective_until->lt($rule->effective_from)) {
            $issues[] = 'invalid_effective_range';
        }
        if ($rule->reviewed_by === null || $rule->reviewed_at === null) {
            $issues[] = 'reviewer_required';
        }
        if ($rule->status !== LegalRuleStatus::Approved) {
            $issues[] = 'must_be_approved';
        }

        $minimum = LegalVerificationLevel::from((string) config('legal.publishing.minimum_verification_level', 'provision_verified'));
        $order = array_flip(array_map(
            static fn (LegalVerificationLevel $level): string => $level->value,
            LegalVerificationLevel::cases(),
        ));
        if (($order[$rule->verification_level->value] ?? 0) < ($order[$minimum->value] ?? 0)) {
            $issues[] = 'verification_level';
        }

        if ((bool) config('legal.publishing.require_distinct_publisher', false)
            && $rule->created_by !== null
            && (int) $actor->id === (int) $rule->created_by) {
            $issues[] = 'distinct_publisher_required';
        }

        foreach ($rule->limits as $limit) {
            if ($limit instanceof LegalRuleLimit) {
                $this->assertLimit($limit);
            }
        }

        try {
            $this->citations->assertPublishable($rule);
        } catch (LegalException $exception) {
            $issues[] = $exception->errorCode();
        }

        $this->conflicts->detectFor($rule);
        if ((bool) config('legal.publishing.block_on_high_severity_conflicts', true)) {
            $blocking = LegalConflict::query()
                ->where(function ($query) use ($rule): void {
                    $query->where('first_rule_id', $rule->id)->orWhere('second_rule_id', $rule->id);
                })
                ->get()
                ->first(fn (LegalConflict $conflict): bool => $conflict->isBlocking());
            if ($blocking !== null) {
                throw LegalException::conflictOpen(['conflict_id' => $blocking->public_id]);
            }
        }

        if ($issues !== []) {
            throw LegalException::publicationInvalid(['issues' => $issues]);
        }
    }

    public function assertLimit(LegalRuleLimit $limit): void
    {
        if ($limit->amount !== null && (float) $limit->amount < 0) {
            throw LegalException::publicationInvalid(['reason' => 'negative_limit']);
        }
        if ($limit->minimum_value !== null && $limit->maximum_value !== null
            && (float) $limit->minimum_value > (float) $limit->maximum_value) {
            throw LegalException::publicationInvalid(['reason' => 'limit_min_gt_max']);
        }
    }
}
