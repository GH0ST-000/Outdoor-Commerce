<?php

declare(strict_types=1);

namespace App\Domains\Legal\Queries;

use App\Domains\Geography\Enums\SpatialAssignmentType;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\DTOs\DerivedLegalContextData;
use App\Domains\Legal\DTOs\PeriodAvailabilityQueryData;
use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\CalendarAvailabilityState;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Legal\Enums\LegalConditionType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Services\PeriodAvailabilityEvaluator;
use App\Domains\Legal\Support\SeasonDateRange;

/**
 * Resolves a safe legal snapshot from zone public IDs and season data.
 * Coordinates are not accepted. An unsigned result is never spatially verified,
 * so commerce cannot treat it as a boundary-checked allowance.
 */
final class ResolveDerivedLegalContextQuery
{
    public function __construct(private readonly PeriodAvailabilityEvaluator $seasons) {}

    /**
     * @param  list<string>  $zonePublicIds
     * @param  list<string>  $methodCodes
     */
    public function execute(
        string $activity,
        ?string $speciesSlug,
        ?string $speciesCategoryCode,
        array $zonePublicIds,
        ?string $periodFrom,
        ?string $periodTo,
        ?string $seasonPhase,
        ?string $regionCode,
        array $methodCodes = [],
    ): DerivedLegalContextData {
        $species = null;
        if (is_string($speciesSlug) && $speciesSlug !== '') {
            $species = Species::query()->published()->where('canonical_slug', $speciesSlug)->first();
        }
        if ($activity === '' && $species !== null && in_array($species->activity_type->value, ['hunting', 'fishing'], true)) {
            $activity = $species->activity_type->value;
        }

        $zones = $zonePublicIds === []
            ? collect()
            : SpatialZone::query()->whereIn('public_id', $zonePublicIds)->get();
        $zoneTypes = [];
        foreach ($zones as $zone) {
            $type = $zone->zone_type;
            $zoneTypes[] = $type instanceof \BackedEnum ? $type->value : (string) $type;
        }

        $spatial = LegalConclusion::Unknown;
        $prohibitedEquipment = [];
        $requiredEquipment = [];
        $prohibitedMethods = [];
        if ($zones->isNotEmpty()) {
            $assignments = LegalRuleSpatialZone::query()
                ->with(['rule.conditions'])
                ->publishedEffective(now())
                ->whereIn('spatial_zone_id', $zones->pluck('id'))
                ->orderByDesc('precedence')
                ->get();
            $effects = [];
            foreach ($assignments as $assignment) {
                $rule = $assignment->rule;
                if (! $rule instanceof LegalRule || $rule->status !== LegalRuleStatus::Published) {
                    continue;
                }
                if ($activity !== '' && $rule->activity_type->value !== $activity && $rule->activity_type !== LegalActivityType::Access) {
                    continue;
                }
                $effect = match ($assignment->assignment_type) {
                    SpatialAssignmentType::ProhibitedWithin => LegalConclusion::Prohibited,
                    SpatialAssignmentType::ConditionalWithin, SpatialAssignmentType::ExceptionWithin => LegalConclusion::Conditional,
                    SpatialAssignmentType::AppliesWithin => match ($rule->effect) {
                        LegalRuleEffect::Prohibit => LegalConclusion::Prohibited,
                        LegalRuleEffect::Allow => LegalConclusion::Allowed,
                        default => LegalConclusion::Conditional,
                    },
                    default => null,
                };
                if ($effect === null) {
                    continue;
                }
                $effects[] = $effect->value;
                foreach ($rule->conditions as $condition) {
                    $code = trim((string) $condition->string_value);
                    if ($code === '') {
                        continue;
                    }
                    if ($condition->condition_type === LegalConditionType::Equipment && $rule->effect === LegalRuleEffect::Prohibit) {
                        $prohibitedEquipment[] = $code;
                    }
                    if ($condition->condition_type === LegalConditionType::Equipment && $rule->effect === LegalRuleEffect::Require) {
                        $requiredEquipment[] = $code;
                    }
                    if ($condition->condition_type === LegalConditionType::Method && $rule->effect === LegalRuleEffect::Prohibit) {
                        $prohibitedMethods[] = $code;
                    }
                }
            }
            if (in_array(LegalConclusion::Prohibited->value, $effects, true) && in_array(LegalConclusion::Allowed->value, $effects, true)) {
                $spatial = LegalConclusion::Conflict;
            } elseif (in_array(LegalConclusion::Prohibited->value, $effects, true)) {
                $spatial = LegalConclusion::Prohibited;
            } elseif (in_array(LegalConclusion::Conditional->value, $effects, true)) {
                $spatial = LegalConclusion::Conditional;
            } elseif (in_array(LegalConclusion::Allowed->value, $effects, true)) {
                $spatial = LegalConclusion::Allowed;
            }
        }

        $seasonState = null;
        if ($periodFrom !== null && $periodTo !== null && $species !== null && in_array($activity, ['hunting', 'fishing'], true)) {
            $season = $this->seasons->evaluate(new PeriodAvailabilityQueryData(
                activityType: LegalActivityType::from($activity),
                period: SeasonDateRange::fromInclusiveDates($periodFrom, $periodTo, SeasonDateRange::configuredTimezone()),
                mode: AvailabilityMode::EntirePeriod,
                jurisdictionCode: (string) config('spatial.default_jurisdiction', 'GE'),
                regionCode: $regionCode,
                speciesId: $species->id,
                methodCodes: $methodCodes,
            ), 'ka', 1, 1);
            $seasonState = (string) ($season['results'][0]['overall_state'] ?? CalendarAvailabilityState::Unknown->value);
        }

        $conclusion = $this->combine($spatial, $seasonState);

        $hasSubject = $activity !== '' || $species !== null;
        $completeness = 'insufficient';
        if ($hasSubject && $zones->isNotEmpty() && $conclusion !== LegalConclusion::Unknown) {
            $completeness = 'partial';
        } elseif ($hasSubject) {
            $completeness = 'partial';
        }

        return new DerivedLegalContextData(
            conclusion: $conclusion->value,
            boundaryUncertain: false,
            spatiallyVerified: false,
            activity: $activity,
            speciesId: $species?->id,
            speciesSlug: $species?->canonical_slug ?? $speciesSlug,
            speciesCategoryCode: $speciesCategoryCode,
            zonePublicIds: $zones->pluck('public_id')->map(static fn ($id): string => (string) $id)->all(),
            zoneTypes: array_values(array_unique($zoneTypes)),
            periodFrom: $periodFrom,
            periodTo: $periodTo,
            seasonPhase: $seasonPhase,
            regionCode: $regionCode,
            prohibitedEquipment: array_values(array_unique($prohibitedEquipment)),
            requiredEquipment: array_values(array_unique($requiredEquipment)),
            prohibitedMethods: array_values(array_unique($prohibitedMethods)),
            allowedMethods: array_values($methodCodes),
            completeness: $completeness,
        );
    }

    private function combine(LegalConclusion $spatial, ?string $seasonState): LegalConclusion
    {
        if ($spatial === LegalConclusion::Conflict || $seasonState === CalendarAvailabilityState::Conflict->value) {
            return LegalConclusion::Conflict;
        }
        if ($spatial === LegalConclusion::Prohibited || $seasonState === CalendarAvailabilityState::Closed->value) {
            return LegalConclusion::Prohibited;
        }
        if ($spatial === LegalConclusion::Unknown) {
            return LegalConclusion::Unknown;
        }
        if ($spatial === LegalConclusion::Conditional
            || $seasonState === CalendarAvailabilityState::Conditional->value
            || $seasonState === CalendarAvailabilityState::PartiallyOpen->value) {
            return LegalConclusion::Conditional;
        }

        return $spatial;
    }
}
