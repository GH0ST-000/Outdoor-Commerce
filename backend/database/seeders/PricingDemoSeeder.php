<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Models\PromotionTarget;
use App\Domains\Pricing\Services\PriceScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class PricingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $list = PriceList::query()->firstOrCreate(
            ['code' => 'retail_gel'],
            [
                'name' => 'Retail GEL',
                'currency_code' => 'GEL',
                'status' => PriceListStatus::Active,
                'is_default' => true,
                'priority' => 0,
                'prices_include_tax' => true,
            ],
        );

        $schedule = app(PriceScheduleService::class);
        ProductVariant::query()->limit(20)->get()->each(function (ProductVariant $variant) use ($list, $schedule): void {
            $aggregate = $schedule->findOrCreateAggregate($list, $variant->id);
            if ($aggregate->periods()->exists()) {
                return;
            }

            $schedule->replaceEffective(
                $aggregate,
                amountMinor: 12_999,
                startsAt: CarbonImmutable::now('UTC')->subDays(7),
                endsAt: null,
                actorId: 1,
                expectedVersion: null,
                closePreviousAtStart: true,
            );
        });

        $percent = Promotion::query()->firstOrCreate(
            ['code' => 'demo-10-off'],
            [
                'name' => 'Demo 10% off',
                'status' => PromotionStatus::Draft,
                'discount_type' => DiscountType::Percentage,
                'percentage_basis_points' => 1000,
                'currency_code' => 'GEL',
                'priority' => 10,
                'stacking_mode' => PromotionStackingMode::Exclusive,
                'starts_at' => CarbonImmutable::now('UTC')->subDay(),
            ],
        );

        PromotionTarget::query()->firstOrCreate(
            [
                'promotion_id' => $percent->id,
                'target_type' => PromotionTargetType::AllProducts,
                'target_id' => null,
                'mode' => PromotionTargetMode::Include,
            ],
        );
    }
}
