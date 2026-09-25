<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Hunting\Models\Species;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\CitationPurpose;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalProvision;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalRuleCitation;
use App\Domains\Legal\Models\LegalRuleCondition;
use App\Domains\Legal\Models\LegalRuleException;
use App\Domains\Legal\Models\LegalRuleLimit;
use App\Domains\Legal\Support\LegalSlug;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LegalRuleWriteService
{
    public function __construct(
        private readonly LegalConditionValidator $conditions,
        private readonly LegalPublicCache $cache,
        private readonly LegalAuditRecorder $audit,
        private readonly LegalConflictDetector $conflicts,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, User $actor): LegalRule
    {
        return DB::transaction(function () use ($input, $actor): LegalRule {
            $speciesId = null;
            if (! empty($input['species_id'])) {
                $species = Species::query()->where('public_id', $input['species_id'])->first()
                    ?? throw LegalException::notFound('Species');
                $speciesId = $species->id;
            }

            $rule = LegalRule::query()->create([
                'title' => $input['title'],
                'slug' => $input['slug'] ?? LegalSlug::from((string) $input['title']).'-'.substr((string) Str::uuid(), 0, 8),
                'activity_type' => $input['activity_type'],
                'rule_type' => $input['rule_type'],
                'effect' => $input['effect'],
                'species_id' => $speciesId,
                'jurisdiction_code' => $input['jurisdiction_code'] ?? config('legal.default_jurisdiction'),
                'region_code' => $input['region_code'] ?? null,
                'zone_reference' => $input['zone_reference'] ?? null,
                'effective_from' => $input['effective_from'],
                'effective_until' => $input['effective_until'] ?? null,
                'priority' => (int) ($input['priority'] ?? 100),
                'status' => LegalRuleStatus::Draft,
                'verification_level' => LegalVerificationLevel::Unverified,
                'interpretation_summary' => $input['interpretation_summary'] ?? null,
                'public_notes' => $input['public_notes'] ?? null,
                'internal_notes' => $input['internal_notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncNested($rule, $input, $actor);
            $this->audit->record(AuditEvent::LegalRuleCreated, $actor, 'legal_rule', $rule->public_id);
            $this->cache->bump();

            return $rule->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(LegalRule $rule, array $input, User $actor): LegalRule
    {
        if ($rule->isPublished()) {
            throw LegalException::publicationInvalid(['reason' => 'published_rules_are_immutable']);
        }

        return DB::transaction(function () use ($rule, $input, $actor): LegalRule {
            if (isset($input['content_version']) && (int) $input['content_version'] !== (int) $rule->content_version) {
                throw LegalException::versionConflict();
            }

            $speciesId = $rule->species_id;
            if (array_key_exists('species_id', $input)) {
                $speciesId = null;
                if (! empty($input['species_id'])) {
                    $species = Species::query()->where('public_id', $input['species_id'])->first()
                        ?? throw LegalException::notFound('Species');
                    $speciesId = $species->id;
                }
            }

            $rule->fill(array_intersect_key($input, array_flip([
                'title', 'activity_type', 'rule_type', 'effect', 'jurisdiction_code', 'region_code',
                'zone_reference', 'effective_from', 'effective_until', 'priority',
                'interpretation_summary', 'public_notes', 'internal_notes',
            ])));
            $rule->species_id = $speciesId;
            $rule->updated_by = $actor->id;
            $rule->content_version++;
            $rule->save();
            $this->syncNested($rule, $input, $actor);
            $this->conflicts->detectFor($rule);
            $this->audit->record(AuditEvent::LegalRuleUpdated, $actor, 'legal_rule', $rule->public_id);
            $this->cache->bump();

            return $rule->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function syncNested(LegalRule $rule, array $input, User $actor): void
    {
        if (isset($input['citations']) && is_array($input['citations'])) {
            $rule->citations()->delete();
            foreach ($input['citations'] as $row) {
                $provision = LegalProvision::query()->where('public_id', $row['provision_id'])->first()
                    ?? throw LegalException::notFound('Legal provision');
                LegalRuleCitation::query()->create([
                    'legal_rule_id' => $rule->id,
                    'legal_provision_id' => $provision->id,
                    'citation_purpose' => $row['citation_purpose'] ?? CitationPurpose::Authority->value,
                    'quoted_excerpt' => isset($row['quoted_excerpt']) ? mb_substr((string) $row['quoted_excerpt'], 0, (int) config('legal.excerpt_max', 400)) : null,
                    'citation_note' => $row['citation_note'] ?? null,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                ]);
            }
            if ($rule->citations()->exists()) {
                $rule->verification_level = LegalVerificationLevel::SourceAttached;
                $rule->save();
            }
        }

        if (isset($input['conditions']) && is_array($input['conditions'])) {
            $rule->conditions()->delete();
            foreach ($input['conditions'] as $index => $row) {
                $this->conditions->assertValid($row);
                LegalRuleCondition::query()->create([
                    'legal_rule_id' => $rule->id,
                    'condition_type' => $row['condition_type'],
                    'operator' => $row['operator'],
                    'value_type' => $row['value_type'],
                    'string_value' => $row['string_value'] ?? null,
                    'integer_value' => $row['integer_value'] ?? null,
                    'decimal_value' => $row['decimal_value'] ?? null,
                    'boolean_value' => $row['boolean_value'] ?? null,
                    'date_value' => $row['date_value'] ?? null,
                    'time_value' => $row['time_value'] ?? null,
                    'reference_type' => $row['reference_type'] ?? null,
                    'reference_id' => $row['reference_id'] ?? null,
                    'unit_code' => $row['unit_code'] ?? null,
                    'group_key' => $row['group_key'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? $index),
                ]);
            }
        }

        if (isset($input['limits']) && is_array($input['limits'])) {
            $rule->limits()->delete();
            foreach ($input['limits'] as $row) {
                LegalRuleLimit::query()->create([
                    'legal_rule_id' => $rule->id,
                    'limit_type' => $row['limit_type'],
                    'amount' => $row['amount'] ?? null,
                    'unit' => $row['unit'] ?? null,
                    'period' => $row['period'] ?? 'not_applicable',
                    'minimum_value' => $row['minimum_value'] ?? null,
                    'maximum_value' => $row['maximum_value'] ?? null,
                    'measurement_unit' => $row['measurement_unit'] ?? null,
                    'applies_per' => $row['applies_per'] ?? 'person',
                    'notes' => $row['notes'] ?? null,
                ]);
            }
        }

        if (isset($input['exceptions']) && is_array($input['exceptions'])) {
            $rule->exceptionsFrom()->delete();
            foreach ($input['exceptions'] as $row) {
                $other = LegalRule::query()->where('public_id', $row['exception_rule_id'])->first()
                    ?? throw LegalException::notFound('Exception rule');
                if ($other->id === $rule->id) {
                    throw LegalException::circularRelation();
                }
                LegalRuleException::query()->create([
                    'base_rule_id' => $rule->id,
                    'exception_rule_id' => $other->id,
                    'relationship_type' => $row['relationship_type'],
                    'precedence' => (int) ($row['precedence'] ?? 100),
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                ]);
            }
        }
    }
}
