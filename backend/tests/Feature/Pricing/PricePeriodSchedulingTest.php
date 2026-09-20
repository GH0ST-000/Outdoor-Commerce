<?php

declare(strict_types=1);

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Models\User;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Exceptions\PricePeriodOverlapException;
use App\Domains\Pricing\Exceptions\PriceUnavailableException;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Pricing\Services\EffectiveBasePriceResolver;
use App\Domains\Pricing\Services\PriceScheduleService;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Tests\Support\PricingFixtures;

it('resolves immediate published price and rejects future-only schedules', function (): void {
    $variant = ProductVariant::factory()->active()->create();
    $list = PricingFixtures::retailGelList();
    $aggregate = VariantPrice::factory()->create([
        'price_list_id' => $list->id,
        'product_variant_id' => $variant->id,
    ]);

    PricePeriod::factory()->published()->create([
        'variant_price_id' => $aggregate->id,
        'amount_minor' => 5000,
        'starts_at' => CarbonImmutable::now('UTC')->addDay(),
    ]);

    $resolver = app(EffectiveBasePriceResolver::class);

    expect(fn () => $resolver->resolveForVariant($variant->id, $list->id))
        ->toThrow(PriceUnavailableException::class);
});

it('rejects overlapping published periods', function (): void {
    $variant = ProductVariant::factory()->active()->create();
    $list = PricingFixtures::retailGelList();
    $aggregate = VariantPrice::factory()->create([
        'price_list_id' => $list->id,
        'product_variant_id' => $variant->id,
    ]);

    $start = CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC');
    PricePeriod::factory()->published()->create([
        'variant_price_id' => $aggregate->id,
        'amount_minor' => 1000,
        'starts_at' => $start,
        'ends_at' => null,
    ]);

    $draft = PricePeriod::factory()->create([
        'variant_price_id' => $aggregate->id,
        'amount_minor' => 1200,
        'starts_at' => $start->addHours(12),
    ]);

    $service = app(PriceScheduleService::class);

    $actor = User::factory()->create();

    expect(fn () => $service->publishPeriod($draft, $actor->id))
        ->toThrow(PricePeriodOverlapException::class);
});

it('honors exclusive end boundary', function (): void {
    $variant = ProductVariant::factory()->active()->create();
    $list = PricingFixtures::retailGelList();
    $aggregate = VariantPrice::factory()->create([
        'price_list_id' => $list->id,
        'product_variant_id' => $variant->id,
    ]);

    $start = CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC');
    $end = CarbonImmutable::parse('2026-07-01 00:00:00', 'UTC');

    PricePeriod::factory()->published()->create([
        'variant_price_id' => $aggregate->id,
        'amount_minor' => 2500,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    $clock = Mockery::mock(Clock::class);
    $clock->shouldReceive('now')->andReturn($end);
    app()->instance(Clock::class, $clock);

    $resolver = app(EffectiveBasePriceResolver::class);

    expect(fn () => $resolver->resolveForVariant($variant->id, $list->id, $end))
        ->toThrow(PriceUnavailableException::class);
});

it('allows adjacent non-overlapping periods', function (): void {
    $variant = ProductVariant::factory()->active()->create();
    $list = PricingFixtures::retailGelList();
    $aggregate = VariantPrice::factory()->create([
        'price_list_id' => $list->id,
        'product_variant_id' => $variant->id,
    ]);

    $firstStart = CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC');
    $switch = CarbonImmutable::parse('2026-02-01 00:00:00', 'UTC');

    PricePeriod::factory()->published()->create([
        'variant_price_id' => $aggregate->id,
        'amount_minor' => 1000,
        'starts_at' => $firstStart,
        'ends_at' => $switch,
    ]);

    $draft = PricePeriod::factory()->create([
        'variant_price_id' => $aggregate->id,
        'amount_minor' => 1100,
        'starts_at' => $switch,
    ]);

    $actor = User::factory()->create();
    app(PriceScheduleService::class)->publishPeriod($draft, $actor->id);

    expect($draft->fresh()?->status)->toBe(PricePeriodStatus::Published);
});
