<?php

declare(strict_types=1);

use App\Domains\Legal\Enums\SeasonBoundaryPrecision;
use App\Domains\Legal\Enums\SeasonScheduleType;
use App\Domains\Legal\Enums\SeasonType;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalSeasonDefinition;
use App\Domains\Legal\Support\SeasonDateRange;
use App\Domains\Legal\Support\SeasonSchedule;

it('converts inclusive closing dates to half-open Tbilisi intervals', function (): void {
    $range = SeasonDateRange::fromInclusiveDates('2026-09-01', '2026-09-30', 'Asia/Tbilisi');

    expect($range->startsAt->format('Y-m-d H:i:s'))->toBe('2026-09-01 00:00:00')
        ->and($range->endsAtExclusive->format('Y-m-d H:i:s'))->toBe('2026-10-01 00:00:00')
        ->and($range->localEndDateInclusive)->toBe('2026-09-30')
        ->and($range->durationDays())->toBe(30);
});

it('keeps exact-time boundaries without collapsing them to date-only', function (): void {
    $range = SeasonDateRange::fromInclusiveDates(
        '2026-09-01',
        '2026-09-01',
        'Asia/Tbilisi',
        SeasonBoundaryPrecision::DateTime,
        '06:00:00',
        '18:00:00',
    );

    expect($range->startsAt->format('H:i:s'))->toBe('06:00:00')
        ->and($range->endsAtExclusive->format('H:i:s'))->toBe('18:00:00');
});

it('rejects invalid calendar dates such as April 31 and non-leap 29 February', function (): void {
    expect(fn () => SeasonDateRange::assertYmd('2026-04-31'))->toThrow(LegalException::class);
    expect(fn () => SeasonDateRange::assertYmd('2025-02-29'))->toThrow(LegalException::class);
    SeasonDateRange::assertYmd('2024-02-29');
});

it('rejects empty and inverted intervals', function (): void {
    expect(fn () => SeasonDateRange::fromInclusiveDates('2026-09-02', '2026-09-01', 'Asia/Tbilisi'))
        ->toThrow(LegalException::class);
});

it('resolves same-year recurring seasons', function (): void {
    $definition = new LegalSeasonDefinition([
        'schedule_type' => SeasonScheduleType::AnnualRecurring,
        'season_type' => SeasonType::Opening,
        'timezone' => 'Asia/Tbilisi',
        'boundary_precision' => SeasonBoundaryPrecision::Date,
        'start_month' => 9,
        'start_day' => 1,
        'end_month' => 11,
        'end_day' => 30,
        'crosses_calendar_year' => false,
        'first_season_year' => 2026,
        'last_season_year' => 2026,
    ]);
    $rows = SeasonSchedule::resolve($definition, 2026, 2026);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['season_year'])->toBe(2026)
        ->and($rows[0]['range']->localStartDate)->toBe('2026-09-01')
        ->and($rows[0]['range']->localEndDateInclusive)->toBe('2026-11-30')
        ->and($rows[0]['range']->endsAtExclusive->format('Y-m-d'))->toBe('2026-12-01');
});

it('assigns cross-year occurrences to the opening year', function (): void {
    $definition = new LegalSeasonDefinition([
        'schedule_type' => SeasonScheduleType::AnnualRecurring,
        'timezone' => 'Asia/Tbilisi',
        'boundary_precision' => SeasonBoundaryPrecision::Date,
        'start_month' => 11,
        'start_day' => 1,
        'end_month' => 1,
        'end_day' => 31,
        'crosses_calendar_year' => true,
        'first_season_year' => 2025,
        'last_season_year' => 2025,
    ]);
    $rows = SeasonSchedule::resolve($definition, 2025, 2025);

    expect($rows[0]['season_year'])->toBe(2025)
        ->and($rows[0]['range']->localStartDate)->toBe('2025-11-01')
        ->and($rows[0]['range']->localEndDateInclusive)->toBe('2026-01-31');
});

it('skips recurring 29 February in non-leap years without inventing a fallback', function (): void {
    $definition = new LegalSeasonDefinition([
        'schedule_type' => SeasonScheduleType::AnnualRecurring,
        'timezone' => 'Asia/Tbilisi',
        'boundary_precision' => SeasonBoundaryPrecision::Date,
        'start_month' => 2,
        'start_day' => 29,
        'end_month' => 3,
        'end_day' => 15,
        'crosses_calendar_year' => false,
    ]);
    $leap = SeasonSchedule::resolve($definition, 2024, 2024);
    $common = SeasonSchedule::resolve($definition, 2025, 2025);

    expect($leap)->toHaveCount(1)
        ->and($leap[0]['range']->localStartDate)->toBe('2024-02-29')
        ->and($common)->toHaveCount(0);
    SeasonSchedule::assertMonthDay(2, 29);
    expect(fn () => SeasonSchedule::assertMonthDay(4, 31))->toThrow(LegalException::class);
});

it('detects overlapping half-open intervals', function (): void {
    $left = SeasonDateRange::fromInclusiveDates('2026-09-01', '2026-09-10', 'Asia/Tbilisi');
    $right = SeasonDateRange::fromInclusiveDates('2026-09-10', '2026-09-20', 'Asia/Tbilisi');
    $touching = SeasonDateRange::fromInclusiveDates('2026-09-11', '2026-09-20', 'Asia/Tbilisi');

    expect($left->overlaps($right))->toBeTrue()
        ->and($left->overlaps($touching))->toBeFalse();
});
