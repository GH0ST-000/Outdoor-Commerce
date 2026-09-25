<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\DTOs\LegalEvaluationFactsData;
use App\Domains\Legal\Enums\ConditionOperator;
use App\Domains\Legal\Enums\ConditionValueType;
use App\Domains\Legal\Enums\LegalConclusion;
use App\Domains\Legal\Enums\LegalConditionType;
use App\Domains\Legal\Enums\LegalConflictSeverity;
use App\Domains\Legal\Enums\LegalConflictStatus;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Models\LegalConflict;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleCondition;
use App\Domains\Legal\Models\LegalRuleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class LegalRuleEvaluator
{
    public function __construct(private readonly LegalConflictDetector $conflicts) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(LegalEvaluationFactsData $facts): array
    {
        $at = Carbon::instance(\DateTimeImmutable::createFromInterface($facts->occurredAt));
        $rules = $this->candidates($facts, $at);
        $trace = [];
        $matched = [];
        $unmet = [];

        foreach ($rules as $rule) {
            $condition = $this->matchRule($rule, $facts);
            $trace[] = [
                'rule_id' => $rule->public_id,
                'result' => $condition['result'],
                'reason' => $condition['reason'],
            ];
            if ($condition['result'] === 'match') {
                $matched[] = $rule;
            } elseif ($condition['result'] === 'unmet') {
                $unmet[] = [
                    'rule_id' => $rule->public_id,
                    'conditions' => $condition['missing'],
                ];
            }
        }

        $matched = $this->applyExceptions(collect($matched));
        $openConflicts = $this->conflicts->openAmong($matched);
        $blocking = array_values(array_filter(
            $openConflicts,
            static fn (LegalConflict $conflict): bool => $conflict->severity === LegalConflictSeverity::High
                && $conflict->status !== LegalConflictStatus::Resolved
                && $conflict->status !== LegalConflictStatus::FalsePositive,
        ));

        $outcome = $this->conclude($matched, $unmet, $blocking);
        $citations = [];
        $limits = [];
        foreach ($matched as $rule) {
            foreach ($rule->citations as $citation) {
                $provision = $citation->provision;
                $document = $provision?->version?->document;
                $officialUrl = null;
                if ($document !== null) {
                    $officialUrl = $document->official_url ?? $document->source?->official_base_url;
                }
                $citations[] = [
                    'rule_id' => $rule->public_id,
                    'reference_code' => $provision?->reference_code,
                    'excerpt' => $citation->quoted_excerpt,
                    'is_primary' => $citation->is_primary,
                    'document_title' => $document?->title,
                    'source_name' => $document?->source?->name,
                    'official_url' => $officialUrl,
                ];
            }
            foreach ($rule->limits as $limit) {
                $limits[] = [
                    'rule_id' => $rule->public_id,
                    'limit_type' => $limit->limit_type->value,
                    'amount' => $limit->amount,
                    'unit' => $limit->unit,
                    'period' => $limit->period->value,
                    'applies_per' => $limit->applies_per->value,
                ];
            }
        }

        $verifiedAt = $matched->max(fn (LegalRule $rule) => $rule->published_at?->toIso8601String());

        return [
            'outcome' => $outcome->value,
            'summary' => $this->summary($outcome),
            'evaluated_at' => now()->toIso8601String(),
            'facts' => [
                'activity_type' => $facts->activityType->value,
                'jurisdiction_code' => $facts->jurisdictionCode,
                'species_id' => $facts->speciesId,
                'occurred_at' => $at->toIso8601String(),
            ],
            'matched_rules' => $matched->map(fn (LegalRule $rule): array => [
                'id' => $rule->public_id,
                'title' => $rule->title,
                'effect' => $rule->effect->value,
                'interpretation_summary' => $rule->interpretation_summary,
                'effective_from' => $rule->effective_from->toIso8601String(),
                'effective_until' => $rule->effective_until?->toIso8601String(),
            ])->values()->all(),
            'unmet_conditions' => $unmet,
            'applicable_limits' => $limits,
            'citations' => $citations,
            'conflicts' => array_map(static fn (LegalConflict $conflict): array => [
                'id' => $conflict->public_id,
                'type' => $conflict->conflict_type->value,
                'severity' => $conflict->severity->value,
                'status' => $conflict->status->value,
            ], $openConflicts),
            'verification' => [
                'rules_published' => $matched->count(),
                'unknown_by_default' => true,
            ],
            'last_verified_at' => $verifiedAt,
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
            'trace' => $trace,
        ];
    }

    /**
     * @return Collection<int, LegalRule>
     */
    private function candidates(LegalEvaluationFactsData $facts, Carbon $at)
    {
        return LegalRule::query()
            ->published()
            ->with(['citations.provision.version.document.source', 'conditions', 'limits', 'exceptionsFrom'])
            ->where('activity_type', $facts->activityType)
            ->where('jurisdiction_code', $facts->jurisdictionCode)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $at);
            })
            ->where(function ($query) use ($facts): void {
                $query->whereNull('species_id');
                if ($facts->speciesId !== null) {
                    $query->orWhere('species_id', $facts->speciesId);
                }
            })
            ->orderBy('priority')
            ->get();
    }

    /**
     * @return array{result: string, reason: string, missing: list<string>}
     */
    private function matchRule(LegalRule $rule, LegalEvaluationFactsData $facts): array
    {
        if ($rule->region_code !== null && $facts->regionCode !== null && $rule->region_code !== $facts->regionCode) {
            return ['result' => 'out_of_scope', 'reason' => 'region', 'missing' => []];
        }
        if ($rule->zone_reference !== null && $facts->zoneReference !== null && $rule->zone_reference !== $facts->zoneReference) {
            return ['result' => 'out_of_scope', 'reason' => 'zone', 'missing' => []];
        }

        $missing = [];
        foreach ($rule->conditions as $condition) {
            $check = $this->evaluateCondition($condition, $facts);
            if ($check === 'missing_fact') {
                $missing[] = $condition->condition_type->value;
            } elseif ($check === 'fail') {
                return ['result' => 'no_match', 'reason' => $condition->condition_type->value, 'missing' => []];
            }
        }

        if ($missing !== []) {
            return ['result' => 'unmet', 'reason' => 'missing_facts', 'missing' => $missing];
        }

        return ['result' => 'match', 'reason' => 'conditions_met', 'missing' => []];
    }

    private function evaluateCondition(LegalRuleCondition $condition, LegalEvaluationFactsData $facts): string
    {
        $actual = $this->factValue($condition, $facts);
        if ($condition->operator === ConditionOperator::Exists) {
            return $actual !== null && $actual !== [] && $actual !== '' ? 'pass' : 'fail';
        }
        if ($condition->operator === ConditionOperator::NotExists) {
            return $actual === null || $actual === [] || $actual === '' ? 'pass' : 'fail';
        }
        if ($actual === null) {
            return 'missing_fact';
        }

        $expected = match ($condition->value_type) {
            ConditionValueType::Integer => $condition->integer_value,
            ConditionValueType::Decimal => $condition->decimal_value,
            ConditionValueType::Boolean => $condition->boolean_value,
            ConditionValueType::Date => $condition->date_value?->toDateString(),
            default => $condition->string_value ?? $condition->reference_id,
        };

        return match ($condition->operator) {
            ConditionOperator::Equals => $this->scalarEquals($actual, $expected) ? 'pass' : 'fail',
            ConditionOperator::NotEquals => ! $this->scalarEquals($actual, $expected) ? 'pass' : 'fail',
            ConditionOperator::In => (is_array($actual) ? in_array((string) $expected, $actual, true) : false) ? 'pass' : 'fail',
            ConditionOperator::NotIn => (is_array($actual) ? ! in_array((string) $expected, $actual, true) : true) ? 'pass' : 'fail',
            ConditionOperator::GreaterThan => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected ? 'pass' : 'fail',
            ConditionOperator::GreaterThanOrEqual => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected ? 'pass' : 'fail',
            ConditionOperator::LessThan => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected ? 'pass' : 'fail',
            ConditionOperator::LessThanOrEqual => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected ? 'pass' : 'fail',
            default => 'fail',
        };
    }

    private function factValue(LegalRuleCondition $condition, LegalEvaluationFactsData $facts): mixed
    {
        return match ($condition->condition_type) {
            LegalConditionType::Activity => $facts->activityType->value,
            LegalConditionType::Species => $facts->speciesId,
            LegalConditionType::Jurisdiction => $facts->jurisdictionCode,
            LegalConditionType::Region => $facts->regionCode,
            LegalConditionType::Zone => $facts->zoneReference,
            LegalConditionType::Permit => $facts->permitCodes,
            LegalConditionType::License => $facts->licenseCodes,
            LegalConditionType::Equipment => $facts->equipmentCodes,
            LegalConditionType::Method => $facts->methodCodes,
            LegalConditionType::DailyQuantity, LegalConditionType::SeasonQuantity => $facts->requestedQuantity,
            LegalConditionType::Date => Carbon::instance(\DateTimeImmutable::createFromInterface($facts->occurredAt))->toDateString(),
            default => $facts->userAttributes[$condition->condition_type->value] ?? null,
        };
    }

    private function scalarEquals(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array((string) $expected, array_map('strval', $actual), true);
        }

        return (string) $actual === (string) $expected;
    }

    /**
     * @param  Collection<int, LegalRule>  $matched
     * @return Collection<int, LegalRule>
     */
    private function applyExceptions(Collection $matched): Collection
    {
        $ids = $matched->pluck('id');
        $exceptions = LegalRuleException::query()
            ->whereIn('base_rule_id', $ids)
            ->whereIn('exception_rule_id', $ids)
            ->get();

        $remove = [];
        foreach ($exceptions as $exception) {
            $remove[] = $exception->base_rule_id;
        }

        return $matched->reject(fn (LegalRule $rule): bool => in_array($rule->id, $remove, true))->values();
    }

    /**
     * @param  Collection<int, LegalRule>  $matched
     * @param  list<array<string, mixed>>  $unmet
     * @param  list<LegalConflict>  $blocking
     */
    private function conclude(Collection $matched, array $unmet, array $blocking): LegalConclusion
    {
        if ($blocking !== []) {
            return LegalConclusion::Conflict;
        }
        if ($matched->contains(fn (LegalRule $rule): bool => $rule->effect === LegalRuleEffect::Prohibit)) {
            return LegalConclusion::Prohibited;
        }
        if ($unmet !== [] && $matched->isEmpty()) {
            return LegalConclusion::Conditional;
        }
        if ($matched->contains(fn (LegalRule $rule): bool => in_array($rule->effect, [LegalRuleEffect::Condition, LegalRuleEffect::Require, LegalRuleEffect::Limit], true))) {
            return LegalConclusion::Conditional;
        }
        if ($matched->contains(fn (LegalRule $rule): bool => $rule->effect === LegalRuleEffect::Allow)) {
            return LegalConclusion::Allowed;
        }

        return LegalConclusion::Unknown;
    }

    private function summary(LegalConclusion $outcome): string
    {
        return match ($outcome) {
            LegalConclusion::Allowed => 'Published rules support a conditional or allowed outcome for these facts. This is not legal advice.',
            LegalConclusion::Prohibited => 'A published prohibition applies to these facts. This is not legal advice.',
            LegalConclusion::Conditional => 'Published rules apply only if additional stated conditions are met. This is not legal advice.',
            LegalConclusion::Conflict => 'Published rules conflict for these facts. Human review is required. This is not legal advice.',
            LegalConclusion::Unknown => 'There is not enough verified published evidence to answer. Absence of a prohibition is not permission.',
        };
    }
}
