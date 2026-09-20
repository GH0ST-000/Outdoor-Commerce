<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Exceptions\PricePeriodImmutableException;
use App\Domains\Pricing\Exceptions\PricePeriodOverlapException;
use App\Domains\Pricing\Exceptions\StalePriceVersionException;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Pricing\Support\PricePeriodOverlapGuard;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PriceScheduleService
{
    public function __construct(
        private readonly Clock $clock,
    ) {}

    public function lockAggregate(int $variantPriceId): VariantPrice
    {
        return VariantPrice::query()->whereKey($variantPriceId)->lockForUpdate()->firstOrFail();
    }

    public function assertExpectedVersion(VariantPrice $aggregate, ?int $expectedVersion): void
    {
        if ($expectedVersion === null) {
            return;
        }

        if ($aggregate->version !== $expectedVersion) {
            throw new StalePriceVersionException;
        }
    }

    /**
     * @return list<PricePeriod>
     */
    public function publishedPeriodsForAggregate(int $variantPriceId): array
    {
        return PricePeriod::query()
            ->where('variant_price_id', $variantPriceId)
            ->where('status', PricePeriodStatus::Published)
            ->orderBy('starts_at')
            ->get()
            ->all();
    }

    public function publishPeriod(PricePeriod $period, int $actorId): PricePeriod
    {
        if ($period->status !== PricePeriodStatus::Draft) {
            throw new PricePeriodImmutableException('Only draft periods can be published.');
        }

        return DB::transaction(fn (): PricePeriod => $this->publishDraftPeriod($period, $actorId));
    }

    public function publishDraftPeriod(PricePeriod $period, int $actorId): PricePeriod
    {
        $aggregate = $this->lockAggregate($period->variant_price_id);
        $existing = collect($this->publishedPeriodsForAggregate($aggregate->id));
        PricePeriodOverlapGuard::assertNoOverlapWithPublished(
            $existing,
            PricePeriodOverlapGuard::immutable($period->starts_at),
            $period->ends_at !== null ? PricePeriodOverlapGuard::immutable($period->ends_at) : null,
        );

        $period->update([
            'status' => PricePeriodStatus::Published,
            'published_at' => $this->clock->now(),
            'published_by' => $actorId,
        ]);

        $aggregate->increment('version');

        return $period->fresh() ?? $period;
    }

    public function cancelPeriod(PricePeriod $period, int $actorId): PricePeriod
    {
        if ($period->status !== PricePeriodStatus::Published) {
            throw new PricePeriodImmutableException('Only published periods can be cancelled.');
        }

        $period->update([
            'status' => PricePeriodStatus::Cancelled,
            'cancelled_at' => $this->clock->now(),
            'cancelled_by' => $actorId,
            'ends_at' => $period->ends_at ?? $this->clock->now(),
        ]);

        $period->variantPrice?->increment('version');

        return $period->fresh() ?? $period;
    }

    public function replaceEffective(
        VariantPrice $aggregate,
        int $amountMinor,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
        int $actorId,
        ?int $expectedVersion,
        bool $closePreviousAtStart,
    ): PricePeriod {
        return DB::transaction(function () use ($aggregate, $amountMinor, $startsAt, $endsAt, $actorId, $expectedVersion, $closePreviousAtStart): PricePeriod {
            $locked = $this->lockAggregate($aggregate->id);
            $this->assertExpectedVersion($locked, $expectedVersion);

            if ($closePreviousAtStart) {
                foreach ($this->publishedPeriodsForAggregate($locked->id) as $published) {
                    if ($published->ends_at === null || $published->ends_at->gt($startsAt)) {
                        if ($published->starts_at->gte($startsAt)) {
                            throw new PricePeriodOverlapException;
                        }
                        $published->update(['ends_at' => $startsAt]);
                    }
                }
            }

            $draft = PricePeriod::query()->create([
                'variant_price_id' => $locked->id,
                'amount_minor' => $amountMinor,
                'status' => PricePeriodStatus::Draft,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => $actorId,
            ]);

            return $this->publishDraftPeriod($draft, $actorId);
        });
    }

    public function findOrCreateAggregate(PriceList $list, int $variantId): VariantPrice
    {
        return VariantPrice::query()->firstOrCreate(
            [
                'price_list_id' => $list->id,
                'product_variant_id' => $variantId,
            ],
            ['version' => 0],
        );
    }
}
