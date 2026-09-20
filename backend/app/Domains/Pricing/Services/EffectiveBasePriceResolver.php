<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Pricing\DTOs\EffectiveBasePriceResult;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Exceptions\DefaultPriceListMissingException;
use App\Domains\Pricing\Exceptions\PriceListInactiveException;
use App\Domains\Pricing\Exceptions\PricePeriodOverlapException;
use App\Domains\Pricing\Exceptions\PriceUnavailableException;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Pricing\ValueObjects\Money;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;

final class EffectiveBasePriceResolver
{
    public function __construct(
        private readonly Clock $clock,
        private readonly CurrencyCatalog $currencies,
    ) {}

    public function resolveForVariant(
        int $productVariantId,
        ?int $priceListId = null,
        ?CarbonImmutable $effectiveAt = null,
    ): EffectiveBasePriceResult {
        $at = $effectiveAt ?? $this->clock->now();
        $list = $this->resolvePriceList($priceListId);

        if (! $list->status->suppliesEffectivePrices()) {
            throw new PriceListInactiveException;
        }

        $aggregate = VariantPrice::query()
            ->where('price_list_id', $list->id)
            ->where('product_variant_id', $productVariantId)
            ->first();

        if ($aggregate === null) {
            throw new PriceUnavailableException;
        }

        $periods = PricePeriod::query()
            ->where('variant_price_id', $aggregate->id)
            ->where('status', PricePeriodStatus::Published)
            ->where('starts_at', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $at);
            })
            ->orderBy('starts_at')
            ->get();

        if ($periods->isEmpty()) {
            throw new PriceUnavailableException;
        }

        if ($periods->count() > 1) {
            throw new PricePeriodOverlapException('Multiple overlapping published price periods detected.');
        }

        /** @var PricePeriod $period */
        $period = $periods->first();

        return new EffectiveBasePriceResult(
            amount: Money::of($period->amount_minor, $list->currency_code),
            priceListId: $list->id,
            pricePeriodId: $period->id,
            variantPriceId: $aggregate->id,
            pricingVersion: $aggregate->version,
            startsAt: CarbonImmutable::instance($period->starts_at),
            endsAt: $period->ends_at !== null ? CarbonImmutable::instance($period->ends_at) : null,
        );
    }

    public function resolvePriceList(?int $priceListId): PriceList
    {
        if ($priceListId !== null) {
            return PriceList::query()->findOrFail($priceListId);
        }

        $code = (string) config('pricing.default_price_list_code', 'retail_gel');
        $list = PriceList::query()->where('code', $code)->whereNull('deleted_at')->first();

        if ($list === null) {
            $currency = $this->currencies->defaultCode();
            $list = PriceList::query()
                ->where('currency_code', $currency)
                ->where('is_default', true)
                ->where('status', PriceListStatus::Active)
                ->whereNull('deleted_at')
                ->first();
        }

        if ($list === null) {
            throw new DefaultPriceListMissingException;
        }

        return $list;
    }
}
