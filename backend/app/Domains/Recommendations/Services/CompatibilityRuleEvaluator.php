<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Recommendations\Enums\CompatibilityOperator;
use App\Domains\Recommendations\Enums\CompatibilityRuleType;
use App\Domains\Recommendations\Enums\ExclusionCode;
use App\Domains\Recommendations\Exceptions\RecommendationException;

/**
 * Typed comparisons only. Operators are an enum, never SQL or expressions.
 *
 * @phpstan-type RuleRow array{
 *     type: string,
 *     dimension: string,
 *     operator: string,
 *     value: string|null,
 *     integer_value: int|null,
 *     decimal_value: float|null,
 *     boolean_value: bool|null,
 *     weight: int
 * }
 */
final class CompatibilityRuleEvaluator
{
    /**
     * @param  list<RuleRow>  $rules
     * @param  array<string, list<string>|string|int|float|bool|null>  $context
     * @return array{exclusions: list<string>, prefer: list<string>, penalty: int}
     */
    public function evaluate(array $rules, array $context): array
    {
        $exclusions = [];
        $prefer = [];
        $penalty = 0;

        foreach ($rules as $rule) {
            $operator = CompatibilityOperator::tryFrom($rule['operator']);
            $type = CompatibilityRuleType::tryFrom($rule['type']);
            if ($operator === null || $type === null) {
                throw RecommendationException::invalid('Compatibility operator or rule type is not allowed.');
            }

            $actual = $context[$rule['dimension']] ?? null;
            $matched = $this->matches($operator, $actual, $rule);
            if ($type === CompatibilityRuleType::Exclude && $matched) {
                $exclusions[] = $this->exclusionFor($rule['dimension'])->value;
            }
            if ($type === CompatibilityRuleType::Require && ! $matched) {
                $exclusions[] = $this->exclusionFor($rule['dimension'])->value;
            }
            if (($type === CompatibilityRuleType::Prefer || $type === CompatibilityRuleType::Include) && $matched) {
                $prefer[] = $rule['dimension'];
            }
            if ($type === CompatibilityRuleType::Penalize && $matched) {
                $penalty += max(0, min(20, $rule['weight']));
            }
        }

        return [
            'exclusions' => array_values(array_unique($exclusions)),
            'prefer' => array_values(array_unique($prefer)),
            'penalty' => $penalty,
        ];
    }

    /**
     * @param  list<string>|string|int|float|bool|null  $actual
     * @param  RuleRow  $rule
     */
    public function matches(CompatibilityOperator $operator, mixed $actual, array $rule): bool
    {
        $list = is_array($actual) ? array_map('strval', $actual) : null;
        $scalar = is_scalar($actual) ? (string) $actual : null;
        $expected = $rule['value'];
        $expectedList = $expected === null ? [] : array_values(array_filter(array_map('trim', explode(',', $expected)), static fn (string $item): bool => $item !== ''));

        return match ($operator) {
            CompatibilityOperator::Exists => $actual !== null && $actual !== '' && $actual !== [],
            CompatibilityOperator::NotExists => $actual === null || $actual === '' || $actual === [],
            CompatibilityOperator::Equals => $list !== null
                ? in_array((string) $expected, $list, true)
                : $scalar === $expected,
            CompatibilityOperator::NotEquals => $list !== null
                ? ! in_array((string) $expected, $list, true)
                : $scalar !== $expected,
            CompatibilityOperator::In => $list !== null
                ? array_intersect($list, $expectedList) !== []
                : in_array((string) $scalar, $expectedList, true),
            CompatibilityOperator::NotIn => $list !== null
                ? array_intersect($list, $expectedList) === []
                : ! in_array((string) $scalar, $expectedList, true),
            CompatibilityOperator::GreaterThan => $this->number($actual) > (float) ($rule['decimal_value'] ?? $rule['integer_value'] ?? 0),
            CompatibilityOperator::GreaterThanOrEqual => $this->number($actual) >= (float) ($rule['decimal_value'] ?? $rule['integer_value'] ?? 0),
            CompatibilityOperator::LessThan => $this->number($actual) < (float) ($rule['decimal_value'] ?? $rule['integer_value'] ?? 0),
            CompatibilityOperator::LessThanOrEqual => $this->number($actual) <= (float) ($rule['decimal_value'] ?? $rule['integer_value'] ?? 0),
            CompatibilityOperator::Between => $this->number($actual) >= (float) ($rule['integer_value'] ?? 0)
                && $this->number($actual) <= (float) ($rule['decimal_value'] ?? 0),
        };
    }

    private function number(mixed $actual): float
    {
        return is_numeric($actual) ? (float) $actual : 0.0;
    }

    private function exclusionFor(string $dimension): ExclusionCode
    {
        return match ($dimension) {
            'activity' => ExclusionCode::ActivityIncompatible,
            'species' => ExclusionCode::SpeciesIncompatible,
            'species_category' => ExclusionCode::SpeciesCategoryIncompatible,
            'equipment' => ExclusionCode::EquipmentProhibited,
            'method' => ExclusionCode::MethodProhibited,
            'region' => ExclusionCode::RegionRestricted,
            'zone_type' => ExclusionCode::ZoneRestricted,
            default => ExclusionCode::MissingRequiredProductData,
        };
    }
}
