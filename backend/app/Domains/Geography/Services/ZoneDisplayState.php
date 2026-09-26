<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\Enums\SpatialAssignmentType;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Models\LegalRule;

/**
 * Zone-level display hint for cartography. This is not a point-in-polygon
 * legal conclusion. Point evaluation stays in SpatialLegalEvaluator.
 */
final class ZoneDisplayState
{
    /**
     * @param  iterable<int, LegalRuleSpatialZone>  $assignments
     */
    public function summarize(iterable $assignments, ?string $activity): string
    {
        $rows = [];
        foreach ($assignments as $assignment) {
            if (! $assignment instanceof LegalRuleSpatialZone) {
                continue;
            }
            if ($assignment->assignment_type === SpatialAssignmentType::AppliesOutside
                || $assignment->assignment_type === SpatialAssignmentType::DoesNotApplyWithin) {
                continue;
            }
            $rule = $assignment->rule;
            if (! $rule instanceof LegalRule || $rule->status !== LegalRuleStatus::Published) {
                continue;
            }
            if ($activity !== null && $activity !== ''
                && $rule->activity_type->value !== $activity
                && $rule->activity_type !== LegalActivityType::Access) {
                continue;
            }
            $rows[] = [
                'effect' => $this->effect($assignment, $rule),
                'precedence' => $assignment->precedence,
            ];
        }

        if ($rows === []) {
            return 'unknown';
        }

        $max = max(array_column($rows, 'precedence'));
        $top = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['precedence'] === $max,
        ));
        $values = array_unique(array_map(static fn (array $row): string => $row['effect'], $top));

        if (in_array('prohibited', $values, true) && in_array('allowed', $values, true)) {
            return 'conflict';
        }
        if (in_array('prohibited', $values, true)) {
            return 'prohibited';
        }
        if (in_array('conditional', $values, true)) {
            return 'conditional';
        }
        if (in_array('allowed', $values, true)) {
            return 'allowed';
        }

        return 'unknown';
    }

    private function effect(LegalRuleSpatialZone $assignment, LegalRule $rule): string
    {
        return match ($assignment->assignment_type) {
            SpatialAssignmentType::ProhibitedWithin => 'prohibited',
            SpatialAssignmentType::ConditionalWithin, SpatialAssignmentType::ExceptionWithin => 'conditional',
            SpatialAssignmentType::AppliesWithin => match ($rule->effect) {
                LegalRuleEffect::Prohibit => 'prohibited',
                LegalRuleEffect::Allow => 'allowed',
                default => 'conditional',
            },
            default => 'unknown',
        };
    }
}
