<?php

declare(strict_types=1);

namespace App\Domains\Legal\Support;

use DateTimeImmutable;

/**
 * The union of the two cited migratory-bird openings.
 * Order No. 95 says the third Saturday of August through 15 February.
 * The ministry notice for 2026 says the fourth Saturday, 22 August, through 1 March.
 * The third Saturday is never earlier than 15 August, and 1 March is the later end.
 */
final class DisputedOpeningWindow
{
    public static function overlaps(string $from, string $to): bool
    {
        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        $year = (int) $start->format('Y');

        foreach ([$year - 1, $year, $year + 1] as $seasonYear) {
            $windowStart = new DateTimeImmutable(sprintf('%04d-08-15', $seasonYear));
            $windowEnd = new DateTimeImmutable(sprintf('%04d-03-01', $seasonYear + 1));
            if ($start <= $windowEnd && $end >= $windowStart) {
                return true;
            }
        }

        return false;
    }
}
