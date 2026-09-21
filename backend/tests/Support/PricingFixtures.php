<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\VariantPrice;
use Carbon\CarbonImmutable;

final class PricingFixtures
{
    public static function retailGelList(array $attributes = []): PriceList
    {
        return PriceList::query()->firstOrCreate(
            ['code' => 'retail_gel'],
            array_merge([
                'name' => 'Retail GEL',
                'currency_code' => 'GEL',
                'status' => PriceListStatus::Active,
                'is_default' => true,
                'priority' => 0,
                'prices_include_tax' => true,
            ], $attributes),
        );
    }

    public static function publishedPrice(
        ProductVariant $variant,
        int $amountMinor,
        ?PriceList $list = null,
        ?CarbonImmutable $startsAt = null,
    ): PricePeriod {
        $list ??= self::retailGelList();
        $aggregate = VariantPrice::query()->firstOrCreate(
            [
                'price_list_id' => $list->id,
                'product_variant_id' => $variant->id,
            ],
            ['version' => 0],
        );

        return PricePeriod::query()->create([
            'variant_price_id' => $aggregate->id,
            'amount_minor' => $amountMinor,
            'status' => PricePeriodStatus::Published,
            'starts_at' => $startsAt ?? CarbonImmutable::now('UTC')->subDay(),
            'ends_at' => null,
            'published_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
