<?php

declare(strict_types=1);

namespace App\Domains\Legal\Actions;

use App\Domains\Legal\DTOs\DerivedLegalContextData;
use App\Domains\Legal\Enums\LegalConditionType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalRule;

final class IssueOutdoorContextTokenAction
{
    public function __construct(private readonly VerifyOutdoorContextTokenAction $tokens) {}

    /**
     * @param  array<string, mixed>  $evaluation
     */
    public function fromEvaluation(array $evaluation, ?int $speciesId = null, ?string $speciesSlug = null): string
    {
        $zoneIds = [];
        $zoneTypes = [];
        foreach ($evaluation['matching_zones'] ?? [] as $match) {
            if (! is_array($match)) {
                continue;
            }
            $relation = (string) ($match['classification']['relation'] ?? '');
            if (! in_array($relation, ['inside', 'on_boundary'], true)) {
                continue;
            }
            $id = $match['zone']['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $zoneIds[] = $id;
            }
            $type = $match['zone']['zone_type'] ?? null;
            if (is_string($type) && $type !== '') {
                $zoneTypes[] = $type;
            }
        }

        $ruleIds = [];
        foreach ($evaluation['applied_rules'] ?? [] as $applied) {
            $id = $applied['rule']['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $ruleIds[] = $id;
            }
        }
        $equipment = $this->equipmentFromRules($ruleIds);
        $conclusion = (string) ($evaluation['outcome'] ?? 'unknown');
        $boundary = (bool) ($evaluation['boundary_warning'] ?? false);
        $context = new DerivedLegalContextData(
            conclusion: $conclusion,
            boundaryUncertain: $boundary,
            spatiallyVerified: true,
            activity: (string) ($evaluation['activity_type'] ?? ''),
            speciesId: $speciesId,
            speciesSlug: $speciesSlug,
            speciesCategoryCode: null,
            zonePublicIds: array_values(array_unique($zoneIds)),
            zoneTypes: array_values(array_unique($zoneTypes)),
            periodFrom: null,
            periodTo: null,
            seasonPhase: null,
            regionCode: null,
            prohibitedEquipment: $equipment['prohibited'],
            requiredEquipment: $equipment['required'],
            prohibitedMethods: $equipment['methods'],
            allowedMethods: [],
            completeness: $boundary || $conclusion === 'unknown' || $zoneIds === [] ? 'partial' : 'high',
        );

        return $this->issue($context);
    }

    public function issue(DerivedLegalContextData $context): string
    {
        return $this->tokens->sign($context, now()->addSeconds((int) config('recommendations.token_ttl_seconds', 900))->getTimestamp());
    }

    /**
     * @param  list<string>  $publicIds
     * @return array{prohibited: list<string>, required: list<string>, methods: list<string>}
     */
    private function equipmentFromRules(array $publicIds): array
    {
        $prohibited = [];
        $required = [];
        $methods = [];
        if ($publicIds === []) {
            return ['prohibited' => [], 'required' => [], 'methods' => []];
        }

        $rules = LegalRule::query()->with('conditions')->whereIn('public_id', $publicIds)->get();
        foreach ($rules as $rule) {
            foreach ($rule->conditions as $condition) {
                $code = trim((string) $condition->string_value);
                if ($code === '') {
                    continue;
                }
                if ($condition->condition_type === LegalConditionType::Equipment && $rule->effect === LegalRuleEffect::Prohibit) {
                    $prohibited[] = $code;
                }
                if ($condition->condition_type === LegalConditionType::Equipment && $rule->effect === LegalRuleEffect::Require) {
                    $required[] = $code;
                }
                if ($condition->condition_type === LegalConditionType::Method && $rule->effect === LegalRuleEffect::Prohibit) {
                    $methods[] = $code;
                }
            }
        }

        return [
            'prohibited' => array_values(array_unique($prohibited)),
            'required' => array_values(array_unique($required)),
            'methods' => array_values(array_unique($methods)),
        ];
    }

    public static function rejected(): LegalException
    {
        return new LegalException('The context token is invalid.', 'CONTEXT_TOKEN_INVALID');
    }
}
