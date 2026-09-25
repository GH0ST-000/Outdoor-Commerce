<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\LegalConflictSeverity;
use App\Domains\Legal\Enums\LegalConflictStatus;
use App\Domains\Legal\Enums\LegalConflictType;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\SeasonType;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSeasonOverride;

final class SeasonConflictDetector
{
    public function detectForDefinition(LegalSeasonDefinition $definition): void
    {
        $definition->loadMissing('rule');
        $candidates = LegalSeasonDefinition::query()
            ->where('id', '!=', $definition->id)
            ->whereIn('status', [LegalRuleStatus::Published, LegalRuleStatus::Approved])
            ->where('species_id', $definition->species_id)
            ->where('activity_type', $definition->activity_type)
            ->where('jurisdiction_code', $definition->jurisdiction_code)
            ->get();

        foreach ($candidates as $other) {
            if (! $this->schedulesOverlap($definition, $other)) {
                continue;
            }
            $sameRegion = $definition->region_code === null || $other->region_code === null || $definition->region_code === $other->region_code;
            if (! $sameRegion) {
                continue;
            }
            if ($this->contradictory($definition->season_type, $other->season_type)) {
                $this->persist($definition->legal_rule_id, $other->legal_rule_id, LegalConflictType::TemporalOverlap, LegalConflictSeverity::High);
            }
        }
    }

    public function detectForOverride(LegalSeasonOverride $override): void
    {
        $others = LegalSeasonOverride::query()
            ->where('id', '!=', $override->id)
            ->where('base_season_definition_id', $override->base_season_definition_id)
            ->whereIn('status', [LegalRuleStatus::Published, LegalRuleStatus::Approved])
            ->get();

        foreach ($others as $other) {
            if ($override->starts_at >= $other->ends_at_exclusive || $override->ends_at_exclusive <= $other->starts_at) {
                continue;
            }
            if ($override->override_type !== $other->override_type && $override->precedence === $other->precedence) {
                $this->persist($override->legal_rule_id, $other->legal_rule_id, LegalConflictType::PrecedenceMissing, LegalConflictSeverity::High);
            }
        }
    }

    public function assertPublishable(LegalSeasonDefinition $definition): void
    {
        $this->detectForDefinition($definition);
        $open = LegalConflict::query()
            ->where(function ($query) use ($definition): void {
                $query->where('first_rule_id', $definition->legal_rule_id)
                    ->orWhere('second_rule_id', $definition->legal_rule_id);
            })
            ->where('severity', LegalConflictSeverity::High)
            ->whereIn('status', [LegalConflictStatus::Open, LegalConflictStatus::UnderReview, LegalConflictStatus::Accepted])
            ->exists();
        if ($open && (bool) config('legal.publishing.block_on_high_severity_conflicts', true)) {
            throw LegalException::conflictOpen(['season_id' => $definition->public_id]);
        }
    }

    public function assertOverridePublishable(LegalSeasonOverride $override): void
    {
        $this->detectForOverride($override);
        $open = LegalConflict::query()
            ->where(function ($query) use ($override): void {
                $query->where('first_rule_id', $override->legal_rule_id)
                    ->orWhere('second_rule_id', $override->legal_rule_id);
            })
            ->where('severity', LegalConflictSeverity::High)
            ->whereIn('status', [LegalConflictStatus::Open, LegalConflictStatus::UnderReview, LegalConflictStatus::Accepted])
            ->exists();
        if ($open && $override->precedence === 0 && (bool) config('legal.publishing.block_on_high_severity_conflicts', true)) {
            throw LegalException::conflictOpen(['override_id' => $override->public_id]);
        }
    }

    private function contradictory(SeasonType $left, SeasonType $right): bool
    {
        return $left->isPermission() && $right->isProhibition()
            || $left->isProhibition() && $right->isPermission();
    }

    private function schedulesOverlap(LegalSeasonDefinition $left, LegalSeasonDefinition $right): bool
    {
        if ($left->schedule_type->value === 'fixed_range' && $right->schedule_type->value === 'fixed_range'
            && $left->start_date && $left->end_date && $right->start_date && $right->end_date) {
            return $left->start_date <= $right->end_date && $left->end_date >= $right->start_date;
        }

        return true;
    }

    private function persist(int $first, int $second, LegalConflictType $type, LegalConflictSeverity $severity): void
    {
        $pair = $first < $second ? [$first, $second] : [$second, $first];
        $existing = LegalConflict::query()
            ->where('first_rule_id', $pair[0])
            ->where('second_rule_id', $pair[1])
            ->where('conflict_type', $type)
            ->first();
        if ($existing !== null) {
            return;
        }
        LegalConflict::query()->create([
            'first_rule_id' => $pair[0],
            'second_rule_id' => $pair[1],
            'conflict_type' => $type,
            'severity' => $severity,
            'status' => LegalConflictStatus::Open,
            'detected_at' => now(),
            'detected_by' => 'system',
            'evidence' => 'season_calendar:'.$type->value,
        ]);
    }
}
