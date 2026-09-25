<?php

declare(strict_types=1);

namespace App\Domains\Legal\Support;

use App\Domains\Legal\Enums\SeasonBoundaryPrecision;
use App\Domains\Legal\Exceptions\LegalException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Half-open legal interval [startsAt, endsAtExclusive) in a named IANA zone.
 *
 * Date-only sources: opening is start of the local day; the inclusive closing
 * date converts to the next local day at 00:00 as the exclusive end.
 */
final readonly class SeasonDateRange
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAtExclusive,
        public string $timezone,
        public string $localStartDate,
        public string $localEndDateInclusive,
    ) {
        if ($this->endsAtExclusive <= $this->startsAt) {
            throw LegalException::seasonInvalid('Season interval must be non-empty.');
        }
    }

    public static function configuredTimezone(): string
    {
        return (string) config('legal.timezone', 'Asia/Tbilisi');
    }

    public static function fromInclusiveDates(
        string $startDate,
        string $endDate,
        string $timezone,
        SeasonBoundaryPrecision $precision = SeasonBoundaryPrecision::Date,
        ?string $startTime = null,
        ?string $endTime = null,
    ): self {
        self::assertYmd($startDate);
        self::assertYmd($endDate);
        $tz = new DateTimeZone($timezone);

        [$sy, $sm, $sd] = array_map(intval(...), explode('-', $startDate));
        [$ey, $em, $ed] = array_map(intval(...), explode('-', $endDate));
        if (! checkdate($sm, $sd, $sy) || ! checkdate($em, $ed, $ey)) {
            throw LegalException::seasonInvalid('Invalid calendar date.');
        }

        $starts = self::parseLocalInstant($startDate, $precision === SeasonBoundaryPrecision::DateTime ? $startTime : '00:00:00', $tz);

        if ($precision === SeasonBoundaryPrecision::DateTime && is_string($endTime) && $endTime !== '') {
            $endExclusive = self::parseLocalInstant($endDate, $endTime, $tz);
        } else {
            $endDay = self::parseLocalInstant($endDate, '00:00:00', $tz);
            $endExclusive = $endDay->modify('+1 day');
        }

        return new self($starts, $endExclusive, $timezone, $startDate, $endDate);
    }

    public static function fromQueryDates(string $from, string $to, string $timezone): self
    {
        return self::fromInclusiveDates($from, $to, $timezone);
    }

    public function durationDays(): int
    {
        return (int) $this->startsAt->diff($this->endsAtExclusive)->format('%a');
    }

    public function overlaps(self $other): bool
    {
        return $this->startsAt < $other->endsAtExclusive && $this->endsAtExclusive > $other->startsAt;
    }

    public function covers(self $inner): bool
    {
        return $this->startsAt <= $inner->startsAt && $this->endsAtExclusive >= $inner->endsAtExclusive;
    }

    public function intersect(self $other): ?self
    {
        $start = $this->startsAt > $other->startsAt ? $this->startsAt : $other->startsAt;
        $end = $this->endsAtExclusive < $other->endsAtExclusive ? $this->endsAtExclusive : $other->endsAtExclusive;
        if ($end <= $start) {
            return null;
        }

        $tz = new DateTimeZone($this->timezone);
        $localStart = $start->setTimezone($tz)->format('Y-m-d');
        $inclusiveEnd = $end->modify('-1 second')->setTimezone($tz)->format('Y-m-d');

        return new self($start, $end, $this->timezone, $localStart, $inclusiveEnd);
    }

    public static function assertYmd(string $value): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw LegalException::seasonInvalid('Dates must use Y-m-d.');
        }
        [$y, $m, $d] = array_map(intval(...), explode('-', $value));
        if (! checkdate($m, $d, $y)) {
            throw LegalException::seasonInvalid('Invalid calendar date: '.$value.'. February 29 is accepted only on leap years.');
        }
    }

    public static function normalizeTime(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) !== 1) {
            throw LegalException::seasonInvalid('Times must use HH:MM or HH:MM:SS.');
        }

        return $time;
    }

    private static function parseLocalInstant(string $date, ?string $time, DateTimeZone $tz): DateTimeImmutable
    {
        $stamp = $date.' '.($time ?: '00:00:00');
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $stamp, $tz);
        if ($parsed === false) {
            throw LegalException::seasonInvalid('Unable to parse legal instant.');
        }

        return $parsed;
    }
}
