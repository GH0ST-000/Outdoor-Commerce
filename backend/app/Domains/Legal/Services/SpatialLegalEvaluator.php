<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Geography\DTOs\PointClassificationData;
use App\Domains\Geography\DTOs\PointLookupQueryData;
use App\Domains\Geography\Enums\SpatialAssignmentType;
use App\Domains\Geography\Enums\SpatialRelation;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Geography\Queries\FindZonesContainingPointQuery;
use App\Domains\Legal\DTOs\PeriodAvailabilityQueryData;
use App\Domains\Legal\DTOs\SpatialEvaluationQueryData;
use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\CalendarAvailabilityState;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Legal\Enums\LegalConflictSeverity;
use App\Domains\Legal\Enums\LegalConflictStatus;
use App\Domains\Legal\Enums\LegalConflictType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleCondition;
use App\Domains\Legal\Support\HuntingInteriorProhibition;
use App\Domains\Legal\Support\LegalLogger;
use App\Domains\Legal\Support\SeasonDateRange;
use Illuminate\Support\Carbon;

final class SpatialLegalEvaluator
{
    public function __construct(
        private readonly FindZonesContainingPointQuery $zones,
        private readonly PeriodAvailabilityEvaluator $seasons,
        private readonly LegalConflictDetector $conflicts,
        private readonly LegalLogger $logger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(SpatialEvaluationQueryData $query, string $locale = 'ka'): array
    {
        $at = Carbon::instance(\DateTimeImmutable::createFromInterface($query->occurredAt));
        $matches = $this->zones->execute(new PointLookupQueryData(
            longitude: $query->longitude,
            latitude: $query->latitude,
            at: $at,
            jurisdictionCode: $query->jurisdictionCode,
            activity: $query->activityType->value,
            speciesId: $query->speciesId,
        ));

        $inside = [];
        $boundaryWarning = false;
        $near = false;
        $onBoundary = false;
        foreach ($matches as $match) {
            $relation = $match['classification']->relation;
            if ($match['classification']->nearBoundary) {
                $near = true;
                $boundaryWarning = true;
            }
            if ($relation === SpatialRelation::OnBoundary) {
                $onBoundary = true;
                $boundaryWarning = true;
            }
            if ($relation === SpatialRelation::Inside || $relation === SpatialRelation::OnBoundary) {
                $inside[] = $match;
            }
        }

        $applied = [];
        $citations = [];
        $conditions = [];
        $limits = [];
        $lastVerified = null;
        $effects = [];
        $matchedRules = [];

        foreach ($inside as $match) {
            $geometry = $match['geometry'];
            $assignments = LegalRuleSpatialZone::query()
                ->with(['rule.citations.provision.version.document.source', 'rule.limits', 'rule.conditions'])
                ->publishedEffective($at)
                ->where('spatial_zone_id', $geometry->spatial_zone_id)
                ->where(function ($builder) use ($geometry): void {
                    $builder->whereNull('zone_geometry_version_id')
                        ->orWhere('zone_geometry_version_id', $geometry->id);
                })
                ->orderByDesc('precedence')
                ->get();

            foreach ($assignments as $assignment) {
                $rule = $assignment->rule;
                if (! $rule instanceof LegalRule || $rule->status !== LegalRuleStatus::Published) {
                    continue;
                }
                if ($rule->activity_type !== $query->activityType && $rule->activity_type !== LegalActivityType::Access) {
                    continue;
                }
                if ($rule->species_id !== null && $query->speciesId !== null && $rule->species_id !== $query->speciesId) {
                    continue;
                }
                if ($assignment->assignment_type === SpatialAssignmentType::AppliesOutside) {
                    continue;
                }
                if ($assignment->assignment_type === SpatialAssignmentType::DoesNotApplyWithin) {
                    continue;
                }

                $effect = $this->effectFrom($assignment, $rule);
                $effects[] = [
                    'effect' => $effect,
                    'precedence' => $assignment->precedence,
                    'rule' => $rule,
                    'assignment' => $assignment,
                ];
                $matchedRules[$rule->id] = $rule;
                $applied[] = [
                    'assignment_id' => $assignment->public_id,
                    'assignment_type' => $assignment->assignment_type->value,
                    'precedence' => $assignment->precedence,
                    'rule' => [
                        'id' => $rule->public_id,
                        'title' => $rule->title,
                        'effect' => $rule->effect->value,
                    ],
                    'zone_id' => $geometry->zone->public_id,
                ];
                foreach ($rule->citations as $citation) {
                    $citations[] = [
                        'rule_id' => $rule->public_id,
                        'reference_code' => $citation->provision?->reference_code,
                        'excerpt' => $citation->quoted_excerpt,
                        'is_primary' => $citation->is_primary,
                        'source_name' => $citation->provision?->version?->document?->source?->name,
                    ];
                }
                foreach ($rule->conditions as $condition) {
                    $conditions[] = [
                        'type' => $condition->condition_type->value,
                        'operator' => $condition->operator->value,
                        'value' => $this->publicConditionValue($condition),
                    ];
                }
                foreach ($rule->limits as $limit) {
                    $limits[] = [
                        'type' => $limit->limit_type->value,
                        'amount' => $limit->amount,
                        'unit' => $limit->unit ?? $limit->measurement_unit,
                        'period' => $limit->period->value,
                        'applies_per' => $limit->applies_per->value,
                    ];
                }
                $verified = $rule->reviewed_at?->toIso8601String()
                    ?? $geometry->zone->dataset?->source?->verified_at?->toIso8601String();
                if (is_string($verified) && ($lastVerified === null || $verified > $lastVerified)) {
                    $lastVerified = $verified;
                }
            }
        }

        $openConflicts = $this->conflicts->openAmong(collect(array_values($matchedRules)));
        $blocking = array_values(array_filter(
            $openConflicts,
            static fn (LegalConflict $conflict): bool => $conflict->severity === LegalConflictSeverity::High
                && $conflict->status !== LegalConflictStatus::Resolved
                && $conflict->status !== LegalConflictStatus::FalsePositive,
        ));

        if ($this->hasUnresolvedEffectClash($effects) && $blocking === []) {
            $this->persistSpatialConflict(array_values($matchedRules));
            $openConflicts = $this->conflicts->openAmong(collect(array_values($matchedRules)));
            $blocking = $openConflicts;
        }

        $spatialOutcome = $this->concludeSpatial($effects, $inside === [], $blocking !== []);
        if ($spatialOutcome === LegalConclusion::Unknown && $this->insideHuntingProhibition($query, $inside)) {
            $spatialOutcome = LegalConclusion::Prohibited;
            array_push($citations, ...$this->interiorCitations());
            $conditions[] = [
                'type' => 'location',
                'operator' => 'inside',
                'value' => 'წითელი მხოლოდ ნაკრძალისა და ეროვნული პარკის საზღვარია. გარშემო ზოლი არ იხატება, რადგან ორი ტექსტი სხვადასხვა მანძილს ასახელებს.',
            ];
        }
        $season = null;
        if ($query->from !== null && $query->to !== null) {
            $mode = AvailabilityMode::tryFrom((string) $query->availabilityMode) ?? AvailabilityMode::EntirePeriod;
            $season = $this->seasons->evaluate(new PeriodAvailabilityQueryData(
                activityType: $query->activityType,
                period: SeasonDateRange::fromInclusiveDates(
                    $query->from,
                    $query->to,
                    SeasonDateRange::configuredTimezone(),
                ),
                mode: $mode,
                jurisdictionCode: $query->jurisdictionCode,
                speciesId: $query->speciesId,
                includeTrace: $mode === AvailabilityMode::Timeline,
            ), $locale, 1, $query->speciesId === null ? 50 : 1);
            unset($season['metrics']);
        }

        $outcome = $this->combine($spatialOutcome, $season, $boundaryWarning);

        $this->logger->info('spatial_evaluation', [
            'outcome' => $outcome->value,
            'zone_count' => count($inside),
            'boundary_warning' => $boundaryWarning,
        ]);

        return [
            'coordinate' => [
                'longitude' => $query->longitude,
                'latitude' => $query->latitude,
                'srid' => 4326,
                'coordinate_order' => 'longitude,latitude',
            ],
            'occurred_at' => $at->toIso8601String(),
            'activity_type' => $query->activityType->value,
            'spatial_classification' => $inside === [] ? 'no_matching_zone' : 'matched',
            'boundary_warning' => $boundaryWarning,
            'on_boundary' => $onBoundary,
            'near_boundary' => $near,
            'boundary_tolerance_degrees' => (float) config('spatial.query.boundary_warning_degrees', 0.001),
            'outcome' => $outcome->value,
            'matching_zones' => array_map(
                fn (array $match): array => $this->zoneMatch($match, $locale),
                $matches,
            ),
            'applied_rules' => $applied,
            'seasonal_availability' => $season,
            'conditions' => $conditions,
            'limits' => $limits,
            'conflicts' => array_map(fn (LegalConflict $conflict): array => [
                'id' => $conflict->public_id,
                'type' => $conflict->conflict_type->value,
                'severity' => $conflict->severity->value,
            ], $openConflicts),
            'citations' => $citations,
            'last_verified_at' => $lastVerified,
            'disclaimer' => (string) config('spatial.disclaimer_key'),
        ];
    }

    /**
     * @param  list<array{effect: LegalConclusion, precedence: int, rule: LegalRule}>  $effects
     */
    private function concludeSpatial(array $effects, bool $noZones, bool $blockingConflict): LegalConclusion
    {
        if ($blockingConflict) {
            return LegalConclusion::Conflict;
        }
        if ($noZones || $effects === []) {
            return LegalConclusion::Unknown;
        }

        $ranked = $effects;
        usort($ranked, static fn (array $a, array $b): int => $b['precedence'] <=> $a['precedence']);
        $topPrecedence = $ranked[0]['precedence'];
        $top = array_values(array_filter($ranked, static fn (array $row): bool => $row['precedence'] === $topPrecedence));
        $values = array_unique(array_map(static fn (array $row): string => $row['effect']->value, $top));
        if (count($values) > 1 && in_array(LegalConclusion::Prohibited->value, $values, true) && in_array(LegalConclusion::Allowed->value, $values, true)) {
            return LegalConclusion::Conflict;
        }
        if (in_array(LegalConclusion::Prohibited->value, $values, true)) {
            return LegalConclusion::Prohibited;
        }
        if (in_array(LegalConclusion::Conditional->value, $values, true)) {
            return LegalConclusion::Conditional;
        }
        if (in_array(LegalConclusion::Allowed->value, $values, true)) {
            return LegalConclusion::Allowed;
        }

        return LegalConclusion::Unknown;
    }

    /**
     * @param  list<array{geometry: SpatialZoneGeometryVersion, classification: PointClassificationData}>  $inside
     */
    private function insideHuntingProhibition(SpatialEvaluationQueryData $query, array $inside): bool
    {
        if ($query->activityType !== LegalActivityType::Hunting) {
            return false;
        }

        foreach ($inside as $match) {
            $zoneType = $match['geometry']->zone->zone_type->value;
            if (HuntingInteriorProhibition::applies($query->activityType->value, $zoneType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{rule_id: null, reference_code: string, excerpt: string, is_primary: bool, source_name: string}>
     */
    private function interiorCitations(): array
    {
        return [
            [
                'rule_id' => null,
                'reference_code' => 'order-95:article-3:paragraph-7',
                'excerpt' => 'ნადირობა აკრძალულია, სახელმწიფო ნაკრძალებში და ეროვნულ პარკებში და მათ გარშემო 500-მეტრიან ზონაში, ასევე საქართველოს ქალაქების ადმინისტრაციულ საზღვრებში.',
                'is_primary' => true,
                'source_name' => 'ბრძანება №95',
            ],
            [
                'rule_id' => null,
                'reference_code' => 'mepa-news-26537:body',
                'excerpt' => 'ნადირობა აკრძალულია საქართველოს კანონმდებლობით განსაზღვრულ ტერიტორიებზე, მათ შორის: ქალაქების ადმინისტრაციულ საზღვრებში, სახელმწიფო ნაკრძალებში, ეროვნულ პარკებში, ნუგზარ ზაზანაშვილის სახელობის სამუხის მრავალმხრივი გამოყენების ტერიტორიაზე, ასევე სახელმწიფო ნაკრძალების გარშემო 500-მეტრიან და ეროვნული პარკების გარშემო 250-მეტრიან ზონებში.',
                'is_primary' => false,
                'source_name' => 'სამინისტროს ცნობა, 2026',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $season
     */
    private function combine(LegalConclusion $spatial, ?array $season, bool $uncertain): LegalConclusion
    {
        $seasonState = is_array($season)
            ? (string) ($season['results'][0]['overall_state'] ?? CalendarAvailabilityState::Unknown->value)
            : null;

        if ($spatial === LegalConclusion::Conflict || $seasonState === CalendarAvailabilityState::Conflict->value) {
            return LegalConclusion::Conflict;
        }
        if ($spatial === LegalConclusion::Prohibited || $seasonState === CalendarAvailabilityState::Closed->value) {
            return LegalConclusion::Prohibited;
        }
        if ($spatial === LegalConclusion::Unknown) {
            return LegalConclusion::Unknown;
        }
        if ($uncertain && $spatial === LegalConclusion::Allowed) {
            return LegalConclusion::Unknown;
        }
        if ($spatial === LegalConclusion::Conditional || $seasonState === CalendarAvailabilityState::Conditional->value) {
            return LegalConclusion::Conditional;
        }

        return $spatial;
    }

    private function publicConditionValue(LegalRuleCondition $condition): ?string
    {
        if (is_string($condition->string_value) && $condition->string_value !== '') {
            return $condition->string_value;
        }
        if ($condition->integer_value !== null) {
            return (string) $condition->integer_value;
        }
        if ($condition->decimal_value !== null && $condition->decimal_value !== '') {
            return (string) $condition->decimal_value;
        }
        if ($condition->boolean_value !== null) {
            return $condition->boolean_value ? 'true' : 'false';
        }
        if ($condition->date_value !== null) {
            return $condition->date_value->toDateString();
        }

        return null;
    }

    private function effectFrom(LegalRuleSpatialZone $assignment, LegalRule $rule): LegalConclusion
    {
        return match ($assignment->assignment_type) {
            SpatialAssignmentType::ProhibitedWithin => LegalConclusion::Prohibited,
            SpatialAssignmentType::ConditionalWithin => LegalConclusion::Conditional,
            SpatialAssignmentType::ExceptionWithin => LegalConclusion::Conditional,
            SpatialAssignmentType::AppliesWithin => match ($rule->effect) {
                LegalRuleEffect::Prohibit => LegalConclusion::Prohibited,
                LegalRuleEffect::Allow => LegalConclusion::Allowed,
                default => LegalConclusion::Conditional,
            },
            default => LegalConclusion::Unknown,
        };
    }

    /**
     * @param  list<array{effect: LegalConclusion, precedence: int}>  $effects
     */
    private function hasUnresolvedEffectClash(array $effects): bool
    {
        if ($effects === []) {
            return false;
        }
        $max = max(array_column($effects, 'precedence'));
        $top = array_filter($effects, static fn (array $row): bool => $row['precedence'] === $max);
        $values = array_unique(array_map(static fn (array $row): string => $row['effect']->value, $top));

        return in_array(LegalConclusion::Prohibited->value, $values, true)
            && in_array(LegalConclusion::Allowed->value, $values, true);
    }

    /**
     * @param  array{geometry: SpatialZoneGeometryVersion, classification: PointClassificationData}  $match
     * @return array<string, mixed>
     */
    private function zoneMatch(array $match, string $locale): array
    {
        $zone = $match['geometry']->zone;

        return [
            'zone' => [
                'id' => $zone->public_id,
                'name' => $zone->localizedName($locale),
                'official_name' => $zone->default_name,
                'zone_type' => $zone->zone_type->value,
                'is_fictional' => $zone->is_fictional,
            ],
            'classification' => $match['classification']->toArray(),
            'last_verified_at' => $zone->dataset?->source?->verified_at?->toIso8601String(),
            'srid' => 4326,
            'coordinate_order' => 'longitude,latitude',
        ];
    }

    /**
     * @param  list<LegalRule>  $rules
     */
    private function persistSpatialConflict(array $rules): void
    {
        if (count($rules) < 2) {
            return;
        }
        $first = $rules[0];
        $second = $rules[1];
        $ids = $first->id < $second->id ? [$first->id, $second->id] : [$second->id, $first->id];
        $existing = LegalConflict::query()
            ->where('first_rule_id', $ids[0])
            ->where('second_rule_id', $ids[1])
            ->where('conflict_type', LegalConflictType::ContradictoryEffect)
            ->whereIn('status', [LegalConflictStatus::Open, LegalConflictStatus::UnderReview])
            ->exists();
        if ($existing) {
            return;
        }
        LegalConflict::query()->create([
            'first_rule_id' => $ids[0],
            'second_rule_id' => $ids[1],
            'conflict_type' => LegalConflictType::ContradictoryEffect,
            'severity' => LegalConflictSeverity::High,
            'status' => LegalConflictStatus::Open,
            'detected_at' => now(),
            'detected_by' => 'spatial_evaluator',
            'evidence' => 'Overlapping spatial assignments without reviewed precedence.',
        ]);
    }
}
