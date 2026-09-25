<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\ConditionOperator;
use App\Domains\Legal\Enums\ConditionValueType;
use App\Domains\Legal\Exceptions\LegalException;

final class LegalConditionValidator
{
    /**
     * @var array<string, list<string>>
     */
    private const COMPATIBLE = [
        'string' => ['equals', 'not_equals', 'in', 'not_in', 'exists', 'not_exists'],
        'integer' => ['equals', 'not_equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
        'decimal' => ['equals', 'not_equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
        'boolean' => ['equals', 'exists', 'not_exists'],
        'date' => ['equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'between'],
        'time' => ['equals', 'greater_than', 'less_than', 'between'],
        'reference' => ['equals', 'in', 'not_in', 'exists', 'not_exists'],
    ];

    /**
     * @param  array<string, mixed>  $input
     */
    public function assertValid(array $input): void
    {
        $operator = ConditionOperator::from((string) $input['operator']);
        $valueType = ConditionValueType::from((string) $input['value_type']);
        $allowed = self::COMPATIBLE[$valueType->value] ?? [];
        if (! in_array($operator->value, $allowed, true)) {
            throw LegalException::conditionInvalid(
                'Operator '.$operator->value.' is not valid for '.$valueType->value.' conditions.',
            );
        }

        if (in_array($operator, [ConditionOperator::Exists, ConditionOperator::NotExists], true)) {
            return;
        }

        $hasValue = match ($valueType) {
            ConditionValueType::String, ConditionValueType::Reference => filled($input['string_value'] ?? $input['reference_id'] ?? null),
            ConditionValueType::Integer => array_key_exists('integer_value', $input) && $input['integer_value'] !== null,
            ConditionValueType::Decimal => filled($input['decimal_value'] ?? null),
            ConditionValueType::Boolean => array_key_exists('boolean_value', $input) && $input['boolean_value'] !== null,
            ConditionValueType::Date => filled($input['date_value'] ?? null),
            ConditionValueType::Time => filled($input['time_value'] ?? null),
        };

        if (! $hasValue) {
            throw LegalException::conditionInvalid('A typed value is required for this condition.');
        }
    }
}
