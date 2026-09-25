<?php

declare(strict_types=1);

namespace App\Domains\Legal\Support;

use App\Domains\Legal\Enums\SeasonScheduleType;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalSeasonDefinition;

final class SeasonSchedule
{
    /**
     * Recurring February 29 is valid on the definition. Occurrences exist only
     * in leap years. No silent fallback to 28 February or 1 March.
     */
    public static function assertMonthDay(int $month, int $day): void
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            throw LegalException::seasonInvalid('Invalid month or day.');
        }
        if ($month === 2 && $day === 29) {
            return;
        }
        if (! checkdate($month, $day, 2023)) {
            throw LegalException::seasonInvalid(sprintf('Impossible date %d-%d.', $month, $day));
        }
    }

    public static function crossesYear(int $startMonth, int $startDay, int $endMonth, int $endDay): bool
    {
        if ($startMonth > $endMonth) {
            return true;
        }

        return $startMonth === $endMonth && $startDay > $endDay;
    }

    /**
     * @return list<array{range: SeasonDateRange, season_year: int, skipped_leap: bool}>
     */
    public static function resolve(LegalSeasonDefinition $definition, int $fromYear, int $throughYear): array
    {
        if ($fromYear > $throughYear) {
            throw LegalException::seasonInvalid('Projection from_year must not exceed through_year.');
        }

        if ($definition->schedule_type === SeasonScheduleType::FixedRange) {
            return self::fixed($definition, $fromYear, $throughYear);
        }

        return self::recurring($definition, $fromYear, $throughYear);
    }

    /**
     * @return list<array{range: SeasonDateRange, season_year: int, skipped_leap: bool}>
     */
    private static function fixed(LegalSeasonDefinition $definition, int $fromYear, int $throughYear): array
    {
        if ($definition->start_date === null || $definition->end_date === null) {
            throw LegalException::seasonInvalid('Fixed-range seasons require start_date and end_date.');
        }

        $range = SeasonDateRange::fromInclusiveDates(
            $definition->start_date->toDateString(),
            $definition->end_date->toDateString(),
            $definition->timezone,
            $definition->boundary_precision,
            $definition->start_time,
            $definition->end_time,
        );
        $seasonYear = (int) $range->startsAt->setTimezone(new \DateTimeZone($definition->timezone))->format('Y');
        if ($seasonYear < $fromYear || $seasonYear > $throughYear) {
            return [];
        }
        if ($definition->first_season_year !== null && $seasonYear < $definition->first_season_year) {
            return [];
        }
        if ($definition->last_season_year !== null && $seasonYear > $definition->last_season_year) {
            return [];
        }

        return [[
            'range' => $range,
            'season_year' => $seasonYear,
            'skipped_leap' => false,
        ]];
    }

    /**
     * @return list<array{range: SeasonDateRange, season_year: int, skipped_leap: bool}>
     */
    private static function recurring(LegalSeasonDefinition $definition, int $fromYear, int $throughYear): array
    {
        $startMonth = $definition->start_month;
        $startDay = $definition->start_day;
        $endMonth = $definition->end_month;
        $endDay = $definition->end_day;
        if ($startMonth === null || $startDay === null || $endMonth === null || $endDay === null) {
            throw LegalException::seasonInvalid('Annual seasons require start and end month/day.');
        }

        self::assertMonthDay($startMonth, $startDay);
        self::assertMonthDay($endMonth, $endDay);

        $crosses = $definition->crosses_calendar_year
            || self::crossesYear($startMonth, $startDay, $endMonth, $endDay);

        $rows = [];
        for ($year = $fromYear; $year <= $throughYear; $year++) {
            if ($definition->first_season_year !== null && $year < $definition->first_season_year) {
                continue;
            }
            if ($definition->last_season_year !== null && $year > $definition->last_season_year) {
                continue;
            }

            $endYear = $crosses ? $year + 1 : $year;
            $startOk = checkdate($startMonth, $startDay, $year);
            $endOk = checkdate($endMonth, $endDay, $endYear);
            if (! $startOk || ! $endOk) {
                $rows[] = [
                    'range' => null,
                    'season_year' => $year,
                    'skipped_leap' => true,
                ];

                continue;
            }

            $startDate = sprintf('%04d-%02d-%02d', $year, $startMonth, $startDay);
            $endDate = sprintf('%04d-%02d-%02d', $endYear, $endMonth, $endDay);
            $rows[] = [
                'range' => SeasonDateRange::fromInclusiveDates(
                    $startDate,
                    $endDate,
                    $definition->timezone,
                    $definition->boundary_precision,
                    $definition->start_time,
                    $definition->end_time,
                ),
                'season_year' => $year,
                'skipped_leap' => false,
            ];
        }

        /** @var list<array{range: SeasonDateRange, season_year: int, skipped_leap: bool}> $valid */
        $valid = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['skipped_leap'] === false && $row['range'] instanceof SeasonDateRange,
        ));

        return $valid;
    }
}
