<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\DTOs\PeriodAvailabilityQueryData;
use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\CalendarAvailabilityState;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\SeasonOverrideType;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Models\LegalSeasonOccurrence;
use App\Domains\Legal\Models\LegalSeasonOverride;
use App\Domains\Legal\Support\SeasonDateRange;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final class PeriodAvailabilityEvaluator
{
    public function __construct(
        private readonly AvailabilityTimelineBuilder $timeline,
        private readonly SeasonPresenter $presenter,
        private readonly LegalPublicCache $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(PeriodAvailabilityQueryData $query, string $locale = 'ka', int $page = 1, int $perPage = 10): array
    {
        $params = [
            'activity' => $query->activityType->value,
            'from' => $query->period->localStartDate,
            'to' => $query->period->localEndDateInclusive,
            'mode' => $query->mode->value,
            'jurisdiction' => $query->jurisdictionCode,
            'region' => $query->regionCode,
            'zone' => $query->zoneReference,
            'species' => $query->speciesId,
            'category' => $query->speciesCategoryId,
            'locale' => $locale,
            'page' => $page,
            'per_page' => $perPage,
        ];

        return $this->cache->remember('outdoor.availability', $params, function () use ($query, $locale, $page, $perPage): array {
            return $this->compute($query, $locale, $page, $perPage);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function compute(PeriodAvailabilityQueryData $query, string $locale, int $page, int $perPage): array
    {
        $started = microtime(true);
        $period = $query->period;

        $occurrences = $this->loadOccurrences($query, $period);
        $overrides = $this->loadOverrides($query, $period);

        $speciesIds = $occurrences->pluck('species_id')->merge(
            $query->speciesId !== null ? [$query->speciesId] : [],
        )->unique()->values();

        if ($query->speciesId !== null && ! $speciesIds->contains($query->speciesId)) {
            $speciesIds->push($query->speciesId);
        }

        $species = Species::query()
            ->published()
            ->with(['translations', 'mediaAttachments.asset'])
            ->whereIn('id', $speciesIds)
            ->when($query->speciesId !== null, fn ($builder) => $builder->where('id', $query->speciesId))
            ->orderBy('scientific_name')
            ->get()
            ->keyBy('id');

        $results = [];
        foreach ($species as $record) {
            $results[] = $this->evaluateSpecies(
                $record,
                $query,
                $occurrences->where('species_id', $record->id)->values(),
                $overrides,
                $locale,
            );
        }

        if ($query->speciesId !== null && $species->isEmpty()) {
            $missing = Species::query()->published()->with(['translations', 'mediaAttachments.asset'])->find($query->speciesId);
            if ($missing !== null) {
                $results[] = $this->unknownSpecies($missing, $query, $locale);
            }
        }

        $total = count($results);
        $slice = array_slice($results, ($page - 1) * $perPage, $perPage);
        $unknownCount = count(array_filter($results, fn (array $row): bool => $row['overall_state'] === CalendarAvailabilityState::Unknown->value));
        $conflictCount = count(array_filter($results, fn (array $row): bool => $row['overall_state'] === CalendarAvailabilityState::Conflict->value));

        return [
            'query' => [
                'activity' => $query->activityType->value,
                'from' => $period->localStartDate,
                'to' => $period->localEndDateInclusive,
                'timezone' => $period->timezone,
                'mode' => $query->mode->value,
                'jurisdiction_code' => $query->jurisdictionCode,
                'region_code' => $query->regionCode,
                'zone_reference' => $query->zoneReference,
            ],
            'results' => $slice,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'metrics' => [
                'unknown_count' => $unknownCount,
                'conflict_count' => $conflictCount,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ],
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
        ];
    }

    /**
     * @param  Collection<int, LegalSeasonOccurrence>  $occurrences
     * @param  Collection<int, LegalSeasonOverride>  $overrides
     * @return array<string, mixed>
     */
    private function evaluateSpecies(
        Species $species,
        PeriodAvailabilityQueryData $query,
        Collection $occurrences,
        Collection $overrides,
        string $locale,
    ): array {
        $period = $query->period;
        $intervals = [];
        $citations = [];
        $limits = [];
        $conditions = [];
        $matchedRules = [];
        $appliedOverrides = [];
        $lastVerified = null;
        $generatedAt = null;

        foreach ($occurrences as $occurrence) {
            $occurrence->loadMissing([
                'definition.rule.citations.provision.version.document.source',
                'definition.rule.limits',
                'definition.rule.conditions',
            ]);
            $definition = $occurrence->definition;
            $rule = $definition?->rule;
            $openConflicts = $rule !== null
                ? app(LegalConflictDetector::class)->openAmong(collect([$rule]))
                : [];
            $state = $openConflicts !== []
                ? CalendarAvailabilityState::Conflict
                : AvailabilityTimelineBuilder::stateFromEffect($occurrence->effect);
            $evidence = $this->ruleEvidence($rule, $definition?->public_id, null, $occurrence->id);
            if (($rule?->effect === LegalRuleEffect::Condition) || ($rule?->conditions?->isNotEmpty() ?? false)) {
                if ($state === CalendarAvailabilityState::Open) {
                    $state = CalendarAvailabilityState::Conditional;
                }
            }
            $intervals[] = [
                'start' => DateTimeImmutable::createFromInterface($occurrence->starts_at),
                'end' => DateTimeImmutable::createFromInterface($occurrence->ends_at_exclusive),
                'state' => $state,
                'evidence' => $evidence,
            ];
            $citations = array_merge($citations, $evidence['citations']);
            $limits = array_merge($limits, $evidence['limits']);
            $conditions = array_merge($conditions, $evidence['conditions']);
            if ($rule !== null) {
                $matchedRules[$rule->public_id] = [
                    'id' => $rule->public_id,
                    'title' => $rule->title,
                    'effect' => $rule->effect->value,
                    'interpretation_summary' => $rule->interpretation_summary,
                ];
            }
            $verified = $rule?->reviewed_at?->toIso8601String();
            if (is_string($verified) && ($lastVerified === null || $verified > $lastVerified)) {
                $lastVerified = $verified;
            }
            $stamp = $occurrence->updated_at?->toIso8601String();
            if (is_string($stamp) && ($generatedAt === null || $stamp > $generatedAt)) {
                $generatedAt = $stamp;
            }
        }

        $speciesOverrides = $overrides->filter(function (LegalSeasonOverride $override) use ($occurrences): bool {
            $baseIds = $occurrences->pluck('season_definition_id');

            return $baseIds->contains($override->base_season_definition_id);
        });

        foreach ($speciesOverrides as $override) {
            $override->loadMissing(['rule.citations.provision.version.document.source', 'rule.limits', 'rule.conditions']);
            $state = match ($override->override_type) {
                SeasonOverrideType::Closure => CalendarAvailabilityState::Closed,
                SeasonOverrideType::SpecialOpening => CalendarAvailabilityState::Open,
                default => CalendarAvailabilityState::Conditional,
            };
            $evidence = $this->ruleEvidence($override->rule, null, $override->public_id, null);
            $evidence['precedence_explicit'] = $override->precedence > 0;
            $intervals[] = [
                'start' => DateTimeImmutable::createFromInterface($override->starts_at),
                'end' => DateTimeImmutable::createFromInterface($override->ends_at_exclusive),
                'state' => $state,
                'evidence' => $evidence,
            ];
            $appliedOverrides[] = [
                'id' => $override->public_id,
                'type' => $override->override_type->value,
                'reason' => $override->reason,
                'precedence' => $override->precedence,
            ];
            $citations = array_merge($citations, $evidence['citations']);
        }

        $segments = $this->timeline->segment($period, $intervals);
        $overall = $this->overall($segments, $query->mode);
        $windows = $this->windows($segments, $period);

        $nextOpening = LegalSeasonOccurrence::query()
            ->current()
            ->where('species_id', $species->id)
            ->where('activity_type', $query->activityType)
            ->where('jurisdiction_code', $query->jurisdictionCode)
            ->where('effect', LegalRuleEffect::Allow)
            ->where('starts_at', '>=', $period->endsAtExclusive)
            ->orderBy('starts_at')
            ->first();
        $nextClosing = LegalSeasonOccurrence::query()
            ->current()
            ->where('species_id', $species->id)
            ->where('activity_type', $query->activityType)
            ->where('jurisdiction_code', $query->jurisdictionCode)
            ->where('ends_at_exclusive', '>', $period->startsAt)
            ->where('effect', LegalRuleEffect::Allow)
            ->orderBy('ends_at_exclusive')
            ->first();

        $trace = $query->includeTrace ? array_map(static fn (array $segment): array => [
            'from' => $segment['start']->format(DATE_ATOM),
            'to_exclusive' => $segment['end']->format(DATE_ATOM),
            'state' => $segment['state']->value,
            'definition_ids' => $segment['evidence']['definition_ids'] ?? [],
            'override_ids' => $segment['evidence']['override_ids'] ?? [],
        ], $segments) : [];

        return [
            'species' => $this->presenter->speciesPublic($species, $locale),
            'activity_type' => $query->activityType->value,
            'overall_state' => $overall->value,
            'requested_period' => [
                'from' => $period->localStartDate,
                'to' => $period->localEndDateInclusive,
                'timezone' => $period->timezone,
            ],
            'mode' => $query->mode->value,
            'available_windows' => $windows['open'],
            'closed_windows' => $windows['closed'],
            'conditional_windows' => $windows['conditional'],
            'unknown_windows' => $windows['unknown'],
            'conflicting_windows' => $windows['conflict'],
            'timeline' => $query->mode === AvailabilityMode::Timeline ? $windows['all'] : [],
            'next_opening' => $nextOpening === null ? null : [
                'at' => $nextOpening->starts_at->toIso8601String(),
                'local_date' => $nextOpening->local_start_date->toDateString(),
            ],
            'next_closing' => $nextClosing === null ? null : [
                'at' => $nextClosing->ends_at_exclusive->toIso8601String(),
                'local_end_date_inclusive' => $nextClosing->local_end_date_inclusive->toDateString(),
            ],
            'conditions' => $this->uniqueRows($conditions),
            'limits' => $this->uniqueRows($limits),
            'permits_licenses' => $this->permitHints($conditions),
            'matched_rules' => array_values($matchedRules),
            'applied_overrides' => $appliedOverrides,
            'citations' => $this->uniqueRows($citations),
            'last_verified_at' => $lastVerified,
            'projection_generated_at' => $generatedAt,
            'freshness' => $lastVerified === null ? 'unverified' : 'verified',
            'region_code' => $query->regionCode,
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
            'trace' => $trace,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unknownSpecies(Species $species, PeriodAvailabilityQueryData $query, string $locale): array
    {
        $period = $query->period;
        $unknownWindow = [[
            'from' => $period->localStartDate,
            'to' => $period->localEndDateInclusive,
            'state' => CalendarAvailabilityState::Unknown->value,
        ]];

        return [
            'species' => $this->presenter->speciesPublic($species, $locale),
            'activity_type' => $query->activityType->value,
            'overall_state' => CalendarAvailabilityState::Unknown->value,
            'requested_period' => [
                'from' => $period->localStartDate,
                'to' => $period->localEndDateInclusive,
                'timezone' => $period->timezone,
            ],
            'mode' => $query->mode->value,
            'available_windows' => [],
            'closed_windows' => [],
            'conditional_windows' => [],
            'unknown_windows' => $unknownWindow,
            'conflicting_windows' => [],
            'timeline' => $query->mode === AvailabilityMode::Timeline ? $unknownWindow : [],
            'next_opening' => null,
            'next_closing' => null,
            'conditions' => [],
            'limits' => [],
            'permits_licenses' => [],
            'matched_rules' => [],
            'applied_overrides' => [],
            'citations' => [],
            'last_verified_at' => null,
            'projection_generated_at' => null,
            'freshness' => 'unverified',
            'region_code' => $query->regionCode,
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
            'trace' => [],
        ];
    }

    /**
     * @param  list<array{start: DateTimeImmutable, end: DateTimeImmutable, state: CalendarAvailabilityState, evidence: array<string, mixed>}>  $segments
     */
    private function overall(array $segments, AvailabilityMode $mode): CalendarAvailabilityState
    {
        $states = array_map(static fn (array $segment): CalendarAvailabilityState => $segment['state'], $segments);
        if (in_array(CalendarAvailabilityState::Conflict, $states, true)) {
            return CalendarAvailabilityState::Conflict;
        }

        if ($mode === AvailabilityMode::EntirePeriod) {
            if (in_array(CalendarAvailabilityState::Unknown, $states, true) || $states === []) {
                return CalendarAvailabilityState::Unknown;
            }
            if (in_array(CalendarAvailabilityState::Closed, $states, true)) {
                return CalendarAvailabilityState::Closed;
            }
            if (in_array(CalendarAvailabilityState::Conditional, $states, true)) {
                return CalendarAvailabilityState::Conditional;
            }

            return CalendarAvailabilityState::Open;
        }

        $hasOpen = false;
        $hasClosed = false;
        $hasConditional = false;
        $hasUnknown = false;
        foreach ($states as $state) {
            $hasOpen = $hasOpen || $state === CalendarAvailabilityState::Open;
            $hasClosed = $hasClosed || $state === CalendarAvailabilityState::Closed;
            $hasConditional = $hasConditional || $state === CalendarAvailabilityState::Conditional;
            $hasUnknown = $hasUnknown || $state === CalendarAvailabilityState::Unknown;
        }
        if ($hasOpen && ($hasClosed || $hasUnknown)) {
            return CalendarAvailabilityState::PartiallyOpen;
        }
        if ($hasOpen && $hasConditional) {
            return CalendarAvailabilityState::PartiallyOpen;
        }
        if ($hasOpen) {
            return CalendarAvailabilityState::Open;
        }
        if ($hasConditional) {
            return CalendarAvailabilityState::Conditional;
        }
        if ($hasClosed && ! $hasUnknown) {
            return CalendarAvailabilityState::Closed;
        }
        if ($hasClosed && $hasUnknown) {
            return CalendarAvailabilityState::PartiallyOpen;
        }

        return CalendarAvailabilityState::Unknown;
    }

    /**
     * @param  list<array{start: DateTimeImmutable, end: DateTimeImmutable, state: CalendarAvailabilityState, evidence: array<string, mixed>}>  $segments
     * @return array<string, list<array<string, mixed>>>
     */
    private function windows(array $segments, SeasonDateRange $period): array
    {
        $grouped = [
            'open' => [],
            'closed' => [],
            'conditional' => [],
            'unknown' => [],
            'conflict' => [],
            'all' => [],
        ];
        $tz = $period->startsAt->getTimezone();
        foreach ($segments as $segment) {
            $row = [
                'from' => $segment['start']->setTimezone($tz)->format('Y-m-d'),
                'to' => $segment['end']->modify('-1 second')->setTimezone($tz)->format('Y-m-d'),
                'from_at' => $segment['start']->format(DATE_ATOM),
                'to_exclusive' => $segment['end']->format(DATE_ATOM),
                'state' => $segment['state']->value,
            ];
            $grouped['all'][] = $row;
            match ($segment['state']) {
                CalendarAvailabilityState::Open, CalendarAvailabilityState::PartiallyOpen => $grouped['open'][] = $row,
                CalendarAvailabilityState::Closed => $grouped['closed'][] = $row,
                CalendarAvailabilityState::Conditional => $grouped['conditional'][] = $row,
                CalendarAvailabilityState::Conflict => $grouped['conflict'][] = $row,
                CalendarAvailabilityState::Unknown => $grouped['unknown'][] = $row,
            };
        }

        return $grouped;
    }

    /**
     * @return Collection<int, LegalSeasonOccurrence>
     */
    private function loadOccurrences(PeriodAvailabilityQueryData $query, SeasonDateRange $period): Collection
    {
        return LegalSeasonOccurrence::query()
            ->current()
            ->overlapping($period->startsAt, $period->endsAtExclusive)
            ->where('activity_type', $query->activityType)
            ->where('jurisdiction_code', $query->jurisdictionCode)
            ->when($query->regionCode !== null, function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->whereNull('region_code')->orWhere('region_code', $query->regionCode);
                });
            })
            ->when($query->speciesId !== null, fn ($builder) => $builder->where('species_id', $query->speciesId))
            ->with(['definition.rule.citations.provision.version.document.source', 'definition.rule.limits', 'definition.rule.conditions'])
            ->get();
    }

    /**
     * @return Collection<int, LegalSeasonOverride>
     */
    private function loadOverrides(PeriodAvailabilityQueryData $query, SeasonDateRange $period): Collection
    {
        return LegalSeasonOverride::query()
            ->published()
            ->where('starts_at', '<', $period->endsAtExclusive)
            ->where('ends_at_exclusive', '>', $period->startsAt)
            ->where('jurisdiction_code', $query->jurisdictionCode)
            ->when($query->regionCode !== null, function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->whereNull('region_code')->orWhere('region_code', $query->regionCode);
                });
            })
            ->with(['rule.citations.provision.version.document.source', 'rule.limits', 'rule.conditions'])
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleEvidence(?LegalRule $rule, ?string $definitionId, ?string $overrideId, mixed $occurrenceId): array
    {
        $citations = [];
        $limits = [];
        $conditions = [];
        if ($rule !== null) {
            foreach ($rule->citations as $citation) {
                $provision = $citation->provision;
                $document = $provision?->version?->document;
                $citations[] = [
                    'rule_id' => $rule->public_id,
                    'reference_code' => $provision?->reference_code,
                    'excerpt' => $citation->quoted_excerpt,
                    'is_primary' => $citation->is_primary,
                    'document_title' => $document?->title,
                    'source_name' => $document?->source?->name,
                    'official_url' => $document === null
                        ? null
                        : ($document->official_url ?? $document->source?->official_base_url),
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
            foreach ($rule->conditions as $condition) {
                $conditions[] = [
                    'rule_id' => $rule->public_id,
                    'condition_type' => $condition->condition_type->value,
                    'operator' => $condition->operator->value,
                    'string_value' => $condition->string_value,
                ];
            }
        }

        return [
            'definition_ids' => array_values(array_filter([$definitionId])),
            'override_ids' => array_values(array_filter([$overrideId])),
            'rule_ids' => array_values(array_filter([$rule?->public_id])),
            'occurrence_id' => $occurrenceId,
            'citations' => $citations,
            'limits' => $limits,
            'conditions' => $conditions,
            'last_verified_at' => $rule?->reviewed_at?->toIso8601String(),
            'precedence_explicit' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function uniqueRows(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = json_encode($row) ?: '';
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $conditions
     * @return list<array<string, mixed>>
     */
    private function permitHints(array $conditions): array
    {
        return array_values(array_filter(
            $conditions,
            static fn (array $row): bool => in_array((string) ($row['condition_type'] ?? ''), ['permit', 'license', 'required_permit', 'required_license'], true)
                || str_contains((string) ($row['condition_type'] ?? ''), 'permit')
                || str_contains((string) ($row['condition_type'] ?? ''), 'license'),
        ));
    }
}
