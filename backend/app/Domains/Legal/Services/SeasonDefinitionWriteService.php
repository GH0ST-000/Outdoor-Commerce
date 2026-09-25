<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Hunting\Models\Species;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\LegalVerificationLevel;
use App\Domains\Legal\Enums\SeasonScheduleType;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Support\SeasonDateRange;
use App\Domains\Legal\Support\SeasonSchedule;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;

final class SeasonDefinitionWriteService
{
    public function __construct(
        private readonly SeasonDefinitionValidator $validator,
        private readonly LegalAuditRecorder $audit,
        private readonly LegalPublicCache $cache,
        private readonly SeasonConflictDetector $conflicts,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, User $actor): LegalSeasonDefinition
    {
        return DB::transaction(function () use ($input, $actor): LegalSeasonDefinition {
            [$rule, $species] = $this->resolveRefs($input);
            $this->validator->assertPayload($input, $rule);
            $attrs = $this->attributes($input, $rule, $species, $actor);
            $attrs['status'] = LegalRuleStatus::Draft;
            $attrs['verification_level'] = LegalVerificationLevel::Unverified;
            $attrs['created_by'] = $actor->id;
            $definition = LegalSeasonDefinition::query()->create($attrs);
            $this->audit->record(AuditEvent::LegalSeasonCreated, $actor, 'legal_season_definition', $definition->public_id);
            $this->conflicts->detectForDefinition($definition);
            $this->cache->bump();

            return $definition->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(LegalSeasonDefinition $definition, array $input, User $actor): LegalSeasonDefinition
    {
        if (! in_array($definition->status, [LegalRuleStatus::Draft, LegalRuleStatus::Rejected, LegalRuleStatus::InReview], true)) {
            throw LegalException::seasonInvalid('Published seasons cannot be edited. Supersede and create a revision.');
        }

        return DB::transaction(function () use ($definition, $input, $actor): LegalSeasonDefinition {
            $merged = array_merge($this->currentPayload($definition), $input);
            [$rule, $species] = $this->resolveRefs($merged);
            $this->validator->assertPayload($merged, $rule);
            $definition->fill($this->attributes($merged, $rule, $species, $actor));
            $definition->content_version = (int) $definition->content_version + 1;
            $definition->updated_by = $actor->id;
            $definition->save();
            $this->audit->record(AuditEvent::LegalSeasonUpdated, $actor, 'legal_season_definition', $definition->public_id);
            $this->conflicts->detectForDefinition($definition);
            $this->cache->bump();

            return $definition->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: LegalRule, 1: Species}
     */
    private function resolveRefs(array $input): array
    {
        $rule = LegalRule::query()->where('public_id', $input['legal_rule_id'])->first()
            ?? throw LegalException::notFound('Legal rule');
        $species = Species::query()->where('public_id', $input['species_id'])->first()
            ?? throw LegalException::notFound('Species');

        return [$rule, $species];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributes(array $input, LegalRule $rule, Species $species, User $actor): array
    {
        $schedule = SeasonScheduleType::from((string) $input['schedule_type']);
        $crosses = false;
        if ($schedule === SeasonScheduleType::AnnualRecurring) {
            $crosses = SeasonSchedule::crossesYear(
                (int) $input['start_month'],
                (int) $input['start_day'],
                (int) $input['end_month'],
                (int) $input['end_day'],
            );
        } elseif (! empty($input['start_date']) && ! empty($input['end_date'])) {
            $crosses = substr((string) $input['start_date'], 0, 4) !== substr((string) $input['end_date'], 0, 4);
        }

        return [
            'legal_rule_id' => $rule->id,
            'species_id' => $species->id,
            'activity_type' => $input['activity_type'],
            'season_type' => $input['season_type'],
            'schedule_type' => $input['schedule_type'],
            'jurisdiction_code' => $input['jurisdiction_code'] ?? $rule->jurisdiction_code,
            'region_code' => $input['region_code'] ?? $rule->region_code,
            'zone_reference' => $input['zone_reference'] ?? $rule->zone_reference,
            'timezone' => $input['timezone'] ?? SeasonDateRange::configuredTimezone(),
            'boundary_precision' => $input['boundary_precision'] ?? 'date',
            'start_date' => $input['start_date'] ?? null,
            'end_date' => $input['end_date'] ?? null,
            'start_month' => $input['start_month'] ?? null,
            'start_day' => $input['start_day'] ?? null,
            'end_month' => $input['end_month'] ?? null,
            'end_day' => $input['end_day'] ?? null,
            'start_time' => isset($input['start_time']) ? SeasonDateRange::normalizeTime((string) $input['start_time']) : null,
            'end_time' => isset($input['end_time']) ? SeasonDateRange::normalizeTime((string) $input['end_time']) : null,
            'first_season_year' => $input['first_season_year'] ?? null,
            'last_season_year' => $input['last_season_year'] ?? null,
            'crosses_calendar_year' => $input['crosses_calendar_year'] ?? $crosses,
            'internal_notes' => $input['internal_notes'] ?? null,
            'updated_by' => $actor->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(LegalSeasonDefinition $definition): array
    {
        $rule = $definition->rule ?? LegalRule::query()->find($definition->legal_rule_id);
        $species = $definition->species ?? Species::query()->find($definition->species_id);

        return [
            'legal_rule_id' => $rule?->public_id,
            'species_id' => $species?->public_id,
            'activity_type' => $definition->activity_type->value,
            'season_type' => $definition->season_type->value,
            'schedule_type' => $definition->schedule_type->value,
            'jurisdiction_code' => $definition->jurisdiction_code,
            'region_code' => $definition->region_code,
            'zone_reference' => $definition->zone_reference,
            'timezone' => $definition->timezone,
            'boundary_precision' => $definition->boundary_precision->value,
            'start_date' => $definition->start_date?->toDateString(),
            'end_date' => $definition->end_date?->toDateString(),
            'start_month' => $definition->start_month,
            'start_day' => $definition->start_day,
            'end_month' => $definition->end_month,
            'end_day' => $definition->end_day,
            'start_time' => $definition->start_time,
            'end_time' => $definition->end_time,
            'first_season_year' => $definition->first_season_year,
            'last_season_year' => $definition->last_season_year,
            'crosses_calendar_year' => $definition->crosses_calendar_year,
            'internal_notes' => null,
        ];
    }
}
