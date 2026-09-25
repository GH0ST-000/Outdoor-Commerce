<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Outdoor;

use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\Species;
use App\Domains\Legal\DTOs\PeriodAvailabilityQueryData;
use App\Domains\Legal\Enums\AvailabilityMode;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalSeasonOccurrence;
use App\Domains\Legal\Services\LegalPublicCache;
use App\Domains\Legal\Services\PeriodAvailabilityEvaluator;
use App\Domains\Legal\Support\SeasonDateRange;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Outdoor\PublicAvailabilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicOutdoorController
{
    public function availability(PublicAvailabilityRequest $request, PeriodAvailabilityEvaluator $evaluator): JsonResponse
    {
        $locale = (string) $request->header('X-Locale', 'ka');
        $speciesId = $this->resolveSpeciesId($request->query('species'));
        $query = new PeriodAvailabilityQueryData(
            activityType: LegalActivityType::from($request->string('activity')->toString()),
            period: $request->period(),
            mode: AvailabilityMode::tryFrom((string) $request->query('mode', 'any_date')) ?? AvailabilityMode::AnyDate,
            jurisdictionCode: (string) ($request->query('jurisdiction') ?: config('legal.default_jurisdiction')),
            regionCode: $request->query('region'),
            speciesId: $speciesId,
        );
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 10)));
        $payload = $evaluator->evaluate($query, $locale, $page, $perPage);
        unset($payload['metrics']);
        foreach ($payload['results'] as &$row) {
            unset($row['trace']);
        }

        return $this->ok($request, $payload);
    }

    public function calendar(Request $request, LegalPublicCache $cache): JsonResponse
    {
        $activity = LegalActivityType::tryFrom((string) $request->query('activity', 'hunting'));
        if ($activity === null || ! in_array($activity, [LegalActivityType::Hunting, LegalActivityType::Fishing], true)) {
            $activity = LegalActivityType::Hunting;
        }
        $month = (string) $request->query('month', (new \DateTimeImmutable('now', new \DateTimeZone(SeasonDateRange::configuredTimezone())))->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = (new \DateTimeImmutable('now', new \DateTimeZone(SeasonDateRange::configuredTimezone())))->format('Y-m');
        }
        $timezone = SeasonDateRange::configuredTimezone();
        $from = $month.'-01';
        SeasonDateRange::assertYmd($from);
        $start = SeasonDateRange::fromInclusiveDates($from, $from, $timezone);
        $endDate = $start->startsAt->modify('last day of this month')->format('Y-m-d');
        $period = SeasonDateRange::fromInclusiveDates($from, $endDate, $timezone);
        $region = $request->query('region');
        $speciesId = $this->resolveSpeciesId($request->query('species'));
        $jurisdiction = (string) ($request->query('jurisdiction') ?: config('legal.default_jurisdiction'));

        $payload = $cache->remember('outdoor.calendar', [
            'activity' => $activity->value,
            'month' => $month,
            'region' => $region,
            'species' => $speciesId,
            'jurisdiction' => $jurisdiction,
        ], function () use ($activity, $period, $region, $speciesId, $jurisdiction, $month): array {
            $rows = LegalSeasonOccurrence::query()
                ->current()
                ->overlapping($period->startsAt, $period->endsAtExclusive)
                ->where('activity_type', $activity)
                ->where('jurisdiction_code', $jurisdiction)
                ->when(is_string($region) && $region !== '', function ($query) use ($region): void {
                    $query->where(function ($inner) use ($region): void {
                        $inner->whereNull('region_code')->orWhere('region_code', $region);
                    });
                })
                ->when($speciesId !== null, fn ($query) => $query->where('species_id', $speciesId))
                ->with('definition')
                ->orderBy('starts_at')
                ->get()
                ->map(fn (LegalSeasonOccurrence $occurrence): array => [
                    'season_year' => $occurrence->season_year,
                    'effect' => $occurrence->effect->value,
                    'local_start_date' => $occurrence->local_start_date->toDateString(),
                    'local_end_date_inclusive' => $occurrence->local_end_date_inclusive->toDateString(),
                    'starts_at' => $occurrence->starts_at->toIso8601String(),
                    'ends_at_exclusive' => $occurrence->ends_at_exclusive->toIso8601String(),
                    'region_code' => $occurrence->region_code,
                    'species_id' => $occurrence->species_id,
                ])
                ->all();

            return [
                'month' => $month,
                'timezone' => $period->timezone,
                'activity' => $activity->value,
                'occurrences' => $rows,
                'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
            ];
        });

        return $this->ok($request, $payload);
    }

    public function transitions(Request $request, LegalPublicCache $cache): JsonResponse
    {
        $activity = LegalActivityType::tryFrom((string) $request->query('activity', 'hunting')) ?? LegalActivityType::Hunting;
        $timezone = SeasonDateRange::configuredTimezone();
        $from = (string) $request->query('from', (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('Y-m-d'));
        SeasonDateRange::assertYmd($from);
        $days = min(90, max(1, (int) $request->query('days', 14)));
        $start = SeasonDateRange::fromInclusiveDates($from, $from, $timezone)->startsAt;
        $end = $start->modify('+'.$days.' days');
        $region = $request->query('region');
        $jurisdiction = (string) ($request->query('jurisdiction') ?: config('legal.default_jurisdiction'));

        $payload = $cache->remember('outdoor.transitions', [
            'activity' => $activity->value,
            'from' => $from,
            'days' => $days,
            'region' => $region,
            'jurisdiction' => $jurisdiction,
        ], function () use ($activity, $start, $end, $region, $jurisdiction, $from, $days, $timezone): array {
            $openings = LegalSeasonOccurrence::query()
                ->current()
                ->where('activity_type', $activity)
                ->where('jurisdiction_code', $jurisdiction)
                ->where('effect', LegalRuleEffect::Allow)
                ->where('starts_at', '>=', $start)
                ->where('starts_at', '<', $end)
                ->when(is_string($region) && $region !== '', function ($query) use ($region): void {
                    $query->where(function ($inner) use ($region): void {
                        $inner->whereNull('region_code')->orWhere('region_code', $region);
                    });
                })
                ->orderBy('starts_at')
                ->limit(50)
                ->get();
            $closings = LegalSeasonOccurrence::query()
                ->current()
                ->where('activity_type', $activity)
                ->where('jurisdiction_code', $jurisdiction)
                ->where('effect', LegalRuleEffect::Allow)
                ->where('ends_at_exclusive', '>', $start)
                ->where('ends_at_exclusive', '<=', $end)
                ->when(is_string($region) && $region !== '', function ($query) use ($region): void {
                    $query->where(function ($inner) use ($region): void {
                        $inner->whereNull('region_code')->orWhere('region_code', $region);
                    });
                })
                ->orderBy('ends_at_exclusive')
                ->limit(50)
                ->get();

            return [
                'from' => $from,
                'days' => $days,
                'timezone' => $timezone,
                'openings' => $openings->map(fn (LegalSeasonOccurrence $row): array => [
                    'at' => $row->starts_at->toIso8601String(),
                    'local_date' => $row->local_start_date->toDateString(),
                    'species_id' => $row->species_id,
                    'season_year' => $row->season_year,
                ])->all(),
                'closings' => $closings->map(fn (LegalSeasonOccurrence $row): array => [
                    'at' => $row->ends_at_exclusive->toIso8601String(),
                    'local_end_date_inclusive' => $row->local_end_date_inclusive->toDateString(),
                    'species_id' => $row->species_id,
                    'season_year' => $row->season_year,
                ])->all(),
                'note' => 'Future occurrences are projections of currently published rules and may change after review.',
                'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
            ];
        });

        return $this->ok($request, $payload);
    }

    public function speciesSeasons(Request $request, string $slug, PeriodAvailabilityEvaluator $evaluator): JsonResponse
    {
        $species = Species::query()->published()->where('canonical_slug', $slug)->first()
            ?? throw SpeciesException::notFound();
        $timezone = SeasonDateRange::configuredTimezone();
        $from = (string) ($request->query('from') ?: (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('Y-m-d'));
        $to = (string) ($request->query('to') ?: (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->modify('+30 days')->format('Y-m-d'));
        SeasonDateRange::assertYmd($from);
        SeasonDateRange::assertYmd($to);
        $period = SeasonDateRange::fromQueryDates($from, $to, $timezone);
        $max = (int) config('legal.calendar.max_public_days', 366);
        if ($period->durationDays() > $max) {
            throw LegalException::seasonRangeExceeded($max);
        }
        $activityValue = $species->activity_type->value;
        $activity = str_contains($activityValue, 'fishing') ? LegalActivityType::Fishing : LegalActivityType::Hunting;
        $query = new PeriodAvailabilityQueryData(
            activityType: $activity,
            period: $period,
            mode: AvailabilityMode::Timeline,
            jurisdictionCode: (string) ($request->query('jurisdiction') ?: config('legal.default_jurisdiction')),
            regionCode: $request->query('region'),
            speciesId: $species->id,
        );
        $payload = $evaluator->evaluate($query, (string) $request->header('X-Locale', 'ka'), 1, 1);
        $row = $payload['results'][0] ?? null;
        if (is_array($row)) {
            unset($row['trace']);
        }

        return $this->ok($request, [
            'species_slug' => $species->canonical_slug,
            'availability' => $row,
            'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
        ]);
    }

    private function resolveSpeciesId(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $species = Species::query()->published()
            ->where(function ($query) use ($value): void {
                $query->where('public_id', $value)->orWhere('canonical_slug', $value);
            })
            ->first();

        return $species?->id;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    private function ok(Request $request, array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                'disclaimer' => (string) config('legal.evaluation.disclaimer_key'),
            ],
        ])->header('Cache-Control', 'public, max-age=30');
    }
}
