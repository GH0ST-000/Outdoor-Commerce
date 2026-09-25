<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Enums\LegalRuleStatus;
use App\Domains\Legal\Enums\SeasonType;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Models\LegalSeasonOccurrence;
use App\Domains\Legal\Support\SeasonDateRange;
use App\Domains\Legal\Support\SeasonSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SeasonOccurrenceGenerator
{
    /**
     * @return array{created: int, updated: int, invalidated: int, skipped_leap_years: list<int>, preview: list<array<string, mixed>>}
     */
    public function generate(LegalSeasonDefinition $definition, int $fromYear, int $throughYear, bool $persist = true): array
    {
        $resolved = SeasonSchedule::resolve($definition, $fromYear, $throughYear);
        $skipped = $this->skippedLeapYears($definition, $fromYear, $throughYear);
        $version = $definition->generationVersion();
        $effect = $this->effectFor($definition->season_type, $definition->rule?->effect);
        $preview = [];
        foreach ($resolved as $row) {
            $range = $row['range'];
            $preview[] = [
                'season_year' => $row['season_year'],
                'starts_at' => $range->startsAt->format(DATE_ATOM),
                'ends_at_exclusive' => $range->endsAtExclusive->format(DATE_ATOM),
                'local_start_date' => $range->localStartDate,
                'local_end_date_inclusive' => $range->localEndDateInclusive,
                'crosses_calendar_year' => $range->localStartDate > $range->localEndDateInclusive
                    || substr($range->localStartDate, 0, 4) !== substr($range->localEndDateInclusive, 0, 4),
                'effect' => $effect->value,
            ];
        }

        if (! $persist) {
            return [
                'created' => 0,
                'updated' => 0,
                'invalidated' => 0,
                'skipped_leap_years' => $skipped,
                'preview' => $preview,
            ];
        }

        if ($definition->status !== LegalRuleStatus::Published) {
            $invalidated = LegalSeasonOccurrence::query()
                ->where('season_definition_id', $definition->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            return [
                'created' => 0,
                'updated' => 0,
                'invalidated' => $invalidated,
                'skipped_leap_years' => $skipped,
                'preview' => $preview,
            ];
        }

        $created = 0;
        $updated = 0;
        $keptYears = [];

        DB::transaction(function () use ($definition, $resolved, $version, $effect, &$created, &$updated, &$keptYears): void {
            $locked = LegalSeasonDefinition::query()->lockForUpdate()->findOrFail($definition->id);
            foreach ($resolved as $row) {
                /** @var SeasonDateRange $range */
                $range = $row['range'];
                $year = $row['season_year'];
                $keptYears[] = $year;
                $attrs = [
                    'species_id' => $locked->species_id,
                    'activity_type' => $locked->activity_type,
                    'jurisdiction_code' => $locked->jurisdiction_code,
                    'region_code' => $locked->region_code,
                    'zone_reference' => $locked->zone_reference,
                    'starts_at' => Carbon::createFromInterface($range->startsAt)->utc(),
                    'ends_at_exclusive' => Carbon::createFromInterface($range->endsAtExclusive)->utc(),
                    'local_start_date' => $range->localStartDate,
                    'local_end_date_inclusive' => $range->localEndDateInclusive,
                    'effect' => $effect,
                    'generation_version' => $version,
                    'definition_updated_at' => $locked->updated_at,
                    'is_current' => true,
                ];
                $existing = LegalSeasonOccurrence::query()
                    ->where('season_definition_id', $locked->id)
                    ->where('season_year', $year)
                    ->lockForUpdate()
                    ->first();
                if ($existing === null) {
                    LegalSeasonOccurrence::query()->create([
                        'season_definition_id' => $locked->id,
                        'season_year' => $year,
                        ...$attrs,
                    ]);
                    $created++;
                } else {
                    $existing->fill($attrs);
                    if ($existing->isDirty()) {
                        $updated++;
                    }
                    $existing->save();
                }
            }
        });

        $invalidated = LegalSeasonOccurrence::query()
            ->where('season_definition_id', $definition->id)
            ->where('is_current', true)
            ->where(function ($query) use ($keptYears, $version): void {
                $query->whereNotIn('season_year', $keptYears ?: [0])
                    ->orWhere('generation_version', '!=', $version);
            })
            ->update(['is_current' => false]);

        $definition->generated_through_year = $throughYear;
        $definition->save();

        return [
            'created' => $created,
            'updated' => $updated,
            'invalidated' => $invalidated,
            'skipped_leap_years' => $skipped,
            'preview' => $preview,
        ];
    }

    /**
     * @return list<int>
     */
    public function skippedLeapYears(LegalSeasonDefinition $definition, int $fromYear, int $throughYear): array
    {
        $months = [$definition->start_month, $definition->end_month];
        $days = [$definition->start_day, $definition->end_day];
        $usesLeap = ($months[0] === 2 && $days[0] === 29) || ($months[1] === 2 && $days[1] === 29);
        if (! $usesLeap) {
            return [];
        }

        $skipped = [];
        for ($year = $fromYear; $year <= $throughYear; $year++) {
            $endYear = $definition->crosses_calendar_year ? $year + 1 : $year;
            $startOk = $definition->start_month === null || checkdate((int) $definition->start_month, (int) $definition->start_day, $year);
            $endOk = $definition->end_month === null || checkdate((int) $definition->end_month, (int) $definition->end_day, $endYear);
            if (! $startOk || ! $endOk) {
                $skipped[] = $year;
            }
        }

        return $skipped;
    }

    private function effectFor(SeasonType $type, ?LegalRuleEffect $ruleEffect): LegalRuleEffect
    {
        if ($type->isProhibition()) {
            return LegalRuleEffect::Prohibit;
        }
        if ($ruleEffect === LegalRuleEffect::Condition) {
            return LegalRuleEffect::Condition;
        }

        return LegalRuleEffect::Allow;
    }
}
