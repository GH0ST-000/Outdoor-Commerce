<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSeasonOverride;
use App\Domains\Legal\Support\SeasonDateRange;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SeasonOverrideWriteService
{
    public function __construct(
        private readonly LegalAuditRecorder $audit,
        private readonly LegalPublicCache $cache,
        private readonly SeasonConflictDetector $conflicts,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, User $actor): LegalSeasonOverride
    {
        return DB::transaction(function () use ($input, $actor): LegalSeasonOverride {
            $definition = LegalSeasonDefinition::query()->where('public_id', $input['base_season_definition_id'])->first()
                ?? throw LegalException::notFound('Season definition');
            $rule = LegalRule::query()->where('public_id', $input['legal_rule_id'])->first()
                ?? throw LegalException::notFound('Legal rule');
            $range = $this->range($input, $definition);
            $override = LegalSeasonOverride::query()->create([
                'base_season_definition_id' => $definition->id,
                'legal_rule_id' => $rule->id,
                'override_type' => $input['override_type'],
                'starts_at' => Carbon::createFromInterface($range->startsAt)->utc(),
                'ends_at_exclusive' => Carbon::createFromInterface($range->endsAtExclusive)->utc(),
                'jurisdiction_code' => $input['jurisdiction_code'] ?? $definition->jurisdiction_code,
                'region_code' => $input['region_code'] ?? $definition->region_code,
                'zone_reference' => $input['zone_reference'] ?? $definition->zone_reference,
                'reason' => $input['reason'],
                'precedence' => (int) ($input['precedence'] ?? 0),
                'status' => LegalRuleStatus::Draft,
                'verification_level' => LegalVerificationLevel::Unverified,
                'internal_notes' => $input['internal_notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->audit->record(AuditEvent::LegalSeasonOverrideCreated, $actor, 'legal_season_override', $override->public_id);
            $this->conflicts->detectForOverride($override);
            $this->cache->bump();

            return $override->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(LegalSeasonOverride $override, array $input, User $actor): LegalSeasonOverride
    {
        if (! in_array($override->status, [LegalRuleStatus::Draft, LegalRuleStatus::Rejected, LegalRuleStatus::InReview], true)) {
            throw LegalException::seasonInvalid('Published overrides cannot be edited.');
        }

        return DB::transaction(function () use ($override, $input, $actor): LegalSeasonOverride {
            $definition = $override->definition;
            if (isset($input['legal_rule_id'])) {
                $rule = LegalRule::query()->where('public_id', $input['legal_rule_id'])->first()
                    ?? throw LegalException::notFound('Legal rule');
                $override->legal_rule_id = $rule->id;
            }
            if (isset($input['start_date'], $input['end_date']) && $definition !== null) {
                $range = $this->range($input, $definition);
                $override->starts_at = Carbon::createFromInterface($range->startsAt)->utc();
                $override->ends_at_exclusive = Carbon::createFromInterface($range->endsAtExclusive)->utc();
            }
            foreach (['override_type', 'jurisdiction_code', 'region_code', 'zone_reference', 'reason', 'precedence', 'internal_notes'] as $field) {
                if (array_key_exists($field, $input)) {
                    $override->{$field} = $input[$field];
                }
            }
            $override->updated_by = $actor->id;
            $override->save();
            $this->audit->record(AuditEvent::LegalSeasonOverrideUpdated, $actor, 'legal_season_override', $override->public_id);
            $this->conflicts->detectForOverride($override);
            $this->cache->bump();

            return $override->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function range(array $input, LegalSeasonDefinition $definition): SeasonDateRange
    {
        return SeasonDateRange::fromInclusiveDates(
            (string) $input['start_date'],
            (string) $input['end_date'],
            (string) ($input['timezone'] ?? $definition->timezone),
            $definition->boundary_precision,
            isset($input['start_time']) ? (string) $input['start_time'] : $definition->start_time,
            isset($input['end_time']) ? (string) $input['end_time'] : $definition->end_time,
        );
    }
}
