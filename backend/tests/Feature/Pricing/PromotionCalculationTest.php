<?php

declare(strict_types=1);

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Models\PromotionTarget;
use App\Domains\Pricing\Services\PromotionCalculator;
use App\Domains\Pricing\Services\PromotionEligibilityService;
use App\Domains\Pricing\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Tests\Support\PricingFixtures;

it('selects best exclusive promotion by lowest final price', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->active()->for($product)->create();
    PricingFixtures::publishedPrice($variant, 10_000);

    $low = Promotion::factory()->active()->create([
        'code' => 'low',
        'percentage_basis_points' => 2000,
        'stacking_mode' => PromotionStackingMode::Exclusive,
        'priority' => 1,
    ]);
    PromotionTarget::query()->create([
        'promotion_id' => $low->id,
        'target_type' => PromotionTargetType::AllProducts,
        'target_id' => null,
        'mode' => PromotionTargetMode::Include,
    ]);

    $high = Promotion::factory()->active()->create([
        'code' => 'high',
        'percentage_basis_points' => 500,
        'stacking_mode' => PromotionStackingMode::Exclusive,
        'priority' => 100,
    ]);
    PromotionTarget::query()->create([
        'promotion_id' => $high->id,
        'target_type' => PromotionTargetType::AllProducts,
        'target_id' => null,
        'mode' => PromotionTargetMode::Include,
    ]);

    $base = Money::of(10_000, 'GEL');
    $eligible = app(PromotionEligibilityService::class)
        ->effectivePromotions('GEL')
        ->filter(fn (Promotion $p) => app(PromotionEligibilityService::class)->isEligible($p, $variant, $product, 'GEL'));

    $result = app(PromotionCalculator::class)->calculate($base, $eligible);

    expect($result['final']->amountMinor)->toBe(8000);
    expect($result['applied'][0]['code'])->toBe('low');
});

it('does not apply paused promotions', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->active()->for($product)->create();

    $promo = Promotion::factory()->create([
        'status' => PromotionStatus::Paused,
        'starts_at' => CarbonImmutable::now('UTC')->subHour(),
    ]);
    PromotionTarget::query()->create([
        'promotion_id' => $promo->id,
        'target_type' => PromotionTargetType::AllProducts,
        'target_id' => null,
        'mode' => PromotionTargetMode::Include,
    ]);

    $eligible = app(PromotionEligibilityService::class)->effectivePromotions('GEL');

    expect($eligible)->toHaveCount(0);
});

it('applies fixed amount discounts in matching currency only', function (): void {
    $base = Money::of(5000, 'GEL');
    $promo = Promotion::factory()->active()->create([
        'discount_type' => DiscountType::FixedAmount,
        'fixed_amount_minor' => 1500,
        'currency_code' => 'GEL',
        'percentage_basis_points' => null,
    ]);
    PromotionTarget::query()->create([
        'promotion_id' => $promo->id,
        'target_type' => PromotionTargetType::AllProducts,
        'target_id' => null,
        'mode' => PromotionTargetMode::Include,
    ]);

    $result = app(PromotionCalculator::class)->calculate($base, collect([$promo]));

    expect($result['final']->amountMinor)->toBe(3500);
});
