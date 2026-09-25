<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use App\Domains\Legal\Enums\CitationPurpose;
use App\Domains\Legal\Enums\ConditionOperator;
use App\Domains\Legal\Enums\ConditionValueType;
use App\Domains\Legal\Enums\ExceptionRelationshipType;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalConditionType;
use App\Domains\Legal\Enums\LegalLimitType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleType;
use App\Domains\Legal\Enums\LimitAppliesPer;
use App\Domains\Legal\Enums\LimitPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLegalRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'activity_type' => [$required, Rule::enum(LegalActivityType::class)],
            'rule_type' => [$required, Rule::enum(LegalRuleType::class)],
            'effect' => [$required, Rule::enum(LegalRuleEffect::class)],
            'species_id' => ['nullable', 'uuid'],
            'jurisdiction_code' => ['nullable', Rule::in(config('legal.jurisdictions', ['GE']))],
            'region_code' => ['nullable', 'string', 'max:64'],
            'zone_reference' => ['nullable', 'string', 'max:128'],
            'effective_from' => [$required, 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'interpretation_summary' => ['nullable', 'string', 'max:4000'],
            'public_notes' => ['nullable', 'string', 'max:4000'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
            'content_version' => ['sometimes', 'integer', 'min:1'],
            'citations' => ['sometimes', 'array'],
            'citations.*.provision_id' => ['required_with:citations', 'uuid'],
            'citations.*.citation_purpose' => ['sometimes', Rule::enum(CitationPurpose::class)],
            'citations.*.quoted_excerpt' => ['nullable', 'string', 'max:400'],
            'citations.*.citation_note' => ['nullable', 'string', 'max:500'],
            'citations.*.is_primary' => ['sometimes', 'boolean'],
            'conditions' => ['sometimes', 'array'],
            'conditions.*.condition_type' => ['required_with:conditions', Rule::enum(LegalConditionType::class)],
            'conditions.*.operator' => ['required_with:conditions', Rule::enum(ConditionOperator::class)],
            'conditions.*.value_type' => ['required_with:conditions', Rule::enum(ConditionValueType::class)],
            'conditions.*.string_value' => ['nullable', 'string', 'max:255'],
            'conditions.*.integer_value' => ['nullable', 'integer'],
            'conditions.*.decimal_value' => ['nullable', 'numeric'],
            'conditions.*.boolean_value' => ['nullable', 'boolean'],
            'conditions.*.date_value' => ['nullable', 'date'],
            'conditions.*.time_value' => ['nullable'],
            'conditions.*.reference_id' => ['nullable', 'string', 'max:64'],
            'conditions.*.reference_type' => ['nullable', 'string', 'max:32'],
            'limits' => ['sometimes', 'array'],
            'limits.*.limit_type' => ['required_with:limits', Rule::enum(LegalLimitType::class)],
            'limits.*.amount' => ['nullable', 'numeric', 'min:0'],
            'limits.*.unit' => ['nullable', 'string', 'max:32'],
            'limits.*.period' => ['sometimes', Rule::enum(LimitPeriod::class)],
            'limits.*.minimum_value' => ['nullable', 'numeric'],
            'limits.*.maximum_value' => ['nullable', 'numeric'],
            'limits.*.applies_per' => ['sometimes', Rule::enum(LimitAppliesPer::class)],
            'exceptions' => ['sometimes', 'array'],
            'exceptions.*.exception_rule_id' => ['required_with:exceptions', 'uuid'],
            'exceptions.*.relationship_type' => ['required_with:exceptions', Rule::enum(ExceptionRelationshipType::class)],
        ];
    }
}
