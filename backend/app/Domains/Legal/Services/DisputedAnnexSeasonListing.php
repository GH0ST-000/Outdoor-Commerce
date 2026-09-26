<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\DTOs\PeriodAvailabilityQueryData;
use App\Domains\Legal\Enums\CalendarAvailabilityState;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Models\LegalRule;
use App\Domains\Legal\Support\DisputedOpeningWindow;
use App\Domains\Legal\Support\SeasonDisplayGroup;

/**
 * Annex rows whose opening day is cited differently by two official texts.
 * They are listed so the species is visible. The card stays a conflict and
 * is not a published permission.
 */
final class DisputedAnnexSeasonListing
{
    /**
     * @var list<string>
     */
    private const RULE_SLUGS = [
        'ge-o95-gallinago-gallinago-other',
        'ge-o95-coturnix-coturnix-other',
        'ge-o95-columba-palumbus-other',
        'ge-o95-columba-livia-other',
        'ge-o95-columba-oenas-other',
        'ge-o95-streptopelia-turtur-other',
    ];

    private const MINISTRY_SLUG = 'ge-mepa-26537-2026-2027-window';

    public function __construct(private readonly SeasonPresenter $presenter) {}

    /**
     * @param  list<int>  $alreadyListedSpeciesIds
     * @return list<array<string, mixed>>
     */
    public function rows(PeriodAvailabilityQueryData $query, string $locale, array $alreadyListedSpeciesIds): array
    {
        if ($query->activityType !== LegalActivityType::Hunting) {
            return [];
        }
        if (! DisputedOpeningWindow::overlaps($query->period->localStartDate, $query->period->localEndDateInclusive)) {
            return [];
        }

        $rules = LegalRule::query()
            ->with(['species.translations'])
            ->whereIn('slug', self::RULE_SLUGS)
            ->where('jurisdiction_code', $query->jurisdictionCode)
            ->where('activity_type', LegalActivityType::Hunting)
            ->get();
        if ($rules->isEmpty()) {
            return [];
        }

        $ministry = LegalRule::query()->where('slug', self::MINISTRY_SLUG)->first();
        $statements = [
            ['source' => 'order_95', 'text' => $this->excerpt((string) $rules->first()?->interpretation_summary)],
            ['source' => 'mepa_2026', 'text' => $this->excerpt((string) ($ministry?->interpretation_summary ?? ''))],
        ];
        $statements = array_values(array_filter(
            $statements,
            static fn (array $row): bool => $row['text'] !== '',
        ));

        $rows = [];
        foreach ($rules as $rule) {
            $species = $rule->species;
            if (! $species instanceof Species) {
                continue;
            }
            if (in_array($species->id, $alreadyListedSpeciesIds, true)) {
                continue;
            }
            if ($query->speciesId !== null && $species->id !== $query->speciesId) {
                continue;
            }
            $rows[] = $this->row($species, $query, $locale, $statements);
        }

        return $rows;
    }

    /**
     * @param  list<array{source: string, text: string}>  $statements
     * @return array<string, mixed>
     */
    private function row(Species $species, PeriodAvailabilityQueryData $query, string $locale, array $statements): array
    {
        $period = $query->period;

        return [
            'species' => $this->presenter->speciesPublic($species, $locale),
            'activity_type' => $query->activityType->value,
            'overall_state' => CalendarAvailabilityState::Conflict->value,
            'group' => SeasonDisplayGroup::key($species->scientific_name),
            'period_statements' => $statements,
            'requested_period' => [
                'from' => $period->localStartDate,
                'to' => $period->localEndDateInclusive,
                'timezone' => $period->timezone,
            ],
            'mode' => $query->mode->value,
            'available_windows' => [],
            'closed_windows' => [],
            'conditional_windows' => [],
            'season_windows' => [],
            'unknown_windows' => [],
            'conflicting_windows' => [],
            'timeline' => [],
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

    private function excerpt(string $summary): string
    {
        $prefix = 'შეუმოწმებელი ამონაწერი. ';
        if (str_starts_with($summary, $prefix)) {
            return trim(mb_substr($summary, mb_strlen($prefix)));
        }

        return trim($summary);
    }
}
