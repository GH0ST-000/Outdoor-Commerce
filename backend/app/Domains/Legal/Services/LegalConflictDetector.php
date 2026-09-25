<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\LegalConflictSeverity;
use App\Domains\Legal\Enums\LegalConflictStatus;
use App\Domains\Legal\Enums\LegalConflictType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleException;
use Illuminate\Support\Collection;

final class LegalConflictDetector
{
    public function detectFor(LegalRule $rule): void
    {
        $candidates = LegalRule::query()
            ->where('id', '!=', $rule->id)
            ->whereIn('status', [LegalRuleStatus::Published, LegalRuleStatus::Approved])
            ->where('activity_type', $rule->activity_type)
            ->where('jurisdiction_code', $rule->jurisdiction_code)
            ->where(function ($query) use ($rule): void {
                $query->whereNull('species_id');
                if ($rule->species_id !== null) {
                    $query->orWhere('species_id', $rule->species_id);
                }
            })
            ->where(function ($query) use ($rule): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $rule->effective_from);
            })
            ->when($rule->effective_until !== null, function ($query) use ($rule): void {
                $query->where('effective_from', '<=', $rule->effective_until);
            })
            ->get();

        foreach ($candidates as $other) {
            if ($this->hasReviewedException($rule, $other)) {
                continue;
            }

            $pair = $this->orderedIds($rule->id, $other->id);
            if ($rule->effect === LegalRuleEffect::Allow && $other->effect === LegalRuleEffect::Prohibit
                || $rule->effect === LegalRuleEffect::Prohibit && $other->effect === LegalRuleEffect::Allow) {
                $this->persist($pair[0], $pair[1], LegalConflictType::ContradictoryEffect, LegalConflictSeverity::High);
            }

            if ($rule->effect === LegalRuleEffect::Limit && $other->effect === LegalRuleEffect::Limit) {
                $this->persist($pair[0], $pair[1], LegalConflictType::OverlappingLimit, LegalConflictSeverity::Medium);
            }
        }
    }

    /**
     * @param  Collection<int, LegalRule>  $rules
     * @return list<LegalConflict>
     */
    public function openAmong(Collection $rules): array
    {
        $ids = $rules->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        return LegalConflict::query()
            ->whereIn('first_rule_id', $ids)
            ->whereIn('second_rule_id', $ids)
            ->whereIn('status', [LegalConflictStatus::Open, LegalConflictStatus::UnderReview, LegalConflictStatus::Accepted])
            ->get()
            ->all();
    }

    private function hasReviewedException(LegalRule $left, LegalRule $right): bool
    {
        return LegalRuleException::query()
            ->where(function ($query) use ($left, $right): void {
                $query->where('base_rule_id', $left->id)->where('exception_rule_id', $right->id);
            })
            ->orWhere(function ($query) use ($left, $right): void {
                $query->where('base_rule_id', $right->id)->where('exception_rule_id', $left->id);
            })
            ->exists();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function orderedIds(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    private function persist(int $first, int $second, LegalConflictType $type, LegalConflictSeverity $severity): void
    {
        $existing = LegalConflict::query()
            ->where('first_rule_id', $first)
            ->where('second_rule_id', $second)
            ->where('conflict_type', $type)
            ->whereIn('status', [LegalConflictStatus::Open, LegalConflictStatus::UnderReview])
            ->first();
        if ($existing !== null) {
            return;
        }

        LegalConflict::query()->create([
            'first_rule_id' => $first,
            'second_rule_id' => $second,
            'conflict_type' => $type,
            'severity' => $severity,
            'status' => LegalConflictStatus::Open,
            'detected_at' => now(),
            'detected_by' => 'system',
            'evidence' => $type->value,
        ]);
    }
}
