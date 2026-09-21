<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Support;

use App\Domains\Pricing\Exceptions\PricePeriodOverlapException;
use App\Domains\Pricing\Models\PricePeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class PricePeriodOverlapGuard
{
    public static function periodsOverlap(
        CarbonImmutable $startA,
        ?CarbonImmutable $endA,
        CarbonImmutable $startB,
        ?CarbonImmutable $endB,
    ): bool {
        $endAEffective = $endA ?? CarbonImmutable::parse('9999-12-31 23:59:59', 'UTC');
        $endBEffective = $endB ?? CarbonImmutable::parse('9999-12-31 23:59:59', 'UTC');

        return $startA->lt($endBEffective) && $startB->lt($endAEffective);
    }

    /**
     * @param  Collection<int, PricePeriod>  $existing
     */
    public static function assertNoOverlapWithPublished(
        Collection $existing,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
        ?int $ignorePeriodId = null,
    ): void {
        foreach ($existing as $period) {
            if ($ignorePeriodId !== null && $period->id === $ignorePeriodId) {
                continue;
            }

            if (self::periodsOverlap(
                $startsAt,
                $endsAt,
                self::immutable($period->starts_at),
                $period->ends_at !== null ? self::immutable($period->ends_at) : null,
            )) {
                throw new PricePeriodOverlapException;
            }
        }
    }

    public static function immutable(CarbonInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value);
    }
}
