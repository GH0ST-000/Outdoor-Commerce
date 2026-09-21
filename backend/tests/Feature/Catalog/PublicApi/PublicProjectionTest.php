<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogVariantProjection;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\PricingFixtures;
use Tests\Support\PublicCatalogFixtures;

it('builds variant and product projections idempotently', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['price' => 11111, 'stock' => 6]);
    $projector = app(PublicCatalogProjector::class);

    $projector->refreshProductGraph($fixture['product']->id);
    $variantRow = PublicCatalogVariantProjection::query()
        ->where('product_variant_id', $fixture['variant']->id)
        ->first();
    $productRow = PublicCatalogProductProjection::query()
        ->where('product_id', $fixture['product']->id)
        ->first();

    expect($variantRow?->is_public)->toBeTrue();
    expect((int) $variantRow?->final_price_minor)->toBe(11111);
    expect($productRow?->is_public)->toBeTrue();
    expect((int) $productRow?->minimum_final_price_minor)->toBe(11111);

    $projector->refreshProductGraph($fixture['product']->id);
    expect(PublicCatalogVariantProjection::query()->where('product_variant_id', $fixture['variant']->id)->count())->toBe(1);
    expect(PublicCatalogProductProjection::query()->where('product_id', $fixture['product']->id)->count())->toBe(1);
});

it('refreshes projections after product inventory price and promotion changes', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['price' => 5000, 'stock' => 4]);

    $fixture['product']->update(['status' => ProductStatus::Archived]);
    PublicCatalogFixtures::rebuild($fixture['product']->id);
    expect(PublicCatalogProductProjection::query()->where('product_id', $fixture['product']->id)->value('is_public'))->toBeFalsy();

    $fixture['product']->update(['status' => ProductStatus::Active]);
    PublicCatalogFixtures::rebuild($fixture['product']->id);
    expect(PublicCatalogProductProjection::query()->where('product_id', $fixture['product']->id)->value('is_public'))->toBeTruthy();

    InventoryBalance::query()->where('product_variant_id', $fixture['variant']->id)->update(['on_hand' => 0]);
    PublicCatalogFixtures::rebuild($fixture['product']->id);
    expect(PublicCatalogVariantProjection::query()->where('product_variant_id', $fixture['variant']->id)->value('is_in_stock'))->toBeFalsy();

    PricePeriod::query()->whereHas('variantPrice', fn ($q) => $q->where('product_variant_id', $fixture['variant']->id))
        ->update(['amount_minor' => 7777]);
    PublicCatalogFixtures::rebuild($fixture['product']->id);
    expect((int) PublicCatalogVariantProjection::query()->where('product_variant_id', $fixture['variant']->id)->value('final_price_minor'))->toBe(7777);

    PublicCatalogFixtures::activePromotion(1000);
    PublicCatalogFixtures::rebuild($fixture['product']->id);
    expect(PublicCatalogVariantProjection::query()->where('product_variant_id', $fixture['variant']->id)->value('on_sale'))->toBeTruthy();
});

it('does not refresh projections when a transaction is rolled back', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    $before = PublicCatalogProductProjection::query()->where('product_id', $fixture['product']->id)->value('updated_at');

    try {
        DB::transaction(function () use ($fixture): void {
            $fixture['product']->update(['is_featured' => true]);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect((bool) $fixture['product']->fresh()?->is_featured)->toBeFalse();
    expect((string) PublicCatalogProductProjection::query()->where('product_id', $fixture['product']->id)->value('updated_at'))
        ->toBe((string) $before);
});

it('refreshes time-sensitive projections at scheduled boundaries and verifies mismatches', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['priced' => false]);
    $list = PricingFixtures::retailGelList();
    $start = CarbonImmutable::now('UTC')->addMinute();
    PricingFixtures::publishedPrice($fixture['variant'], 2222, $list, $start);
    PublicCatalogFixtures::rebuild($fixture['product']->id);

    expect(PublicCatalogVariantProjection::query()->where('product_variant_id', $fixture['variant']->id)->value('is_public'))
        ->toBeFalsy();

    $clock = Mockery::mock(Clock::class);
    $clock->shouldReceive('now')->andReturn($start->addSecond());
    app()->instance(Clock::class, $clock);

    $this->artisan('catalog:refresh-time-sensitive-projections')->assertSuccessful();

    PublicCatalogFixtures::rebuild($fixture['product']->id);
    expect((int) PublicCatalogVariantProjection::query()->where('product_variant_id', $fixture['variant']->id)->value('final_price_minor') ?? 0)
        ->toBeGreaterThan(0);

    $this->artisan('catalog:verify-public-projections', ['--product' => $fixture['product']->id])->assertSuccessful();

    PublicCatalogVariantProjection::query()->where('product_variant_id', $fixture['variant']->id)->update(['final_price_minor' => 1]);
    $this->artisan('catalog:verify-public-projections', ['--variant' => $fixture['variant']->id])->assertFailed();

    $this->artisan('catalog:rebuild-public-projections', ['--dry-run' => true])->assertSuccessful();
    $this->artisan('catalog:rebuild-public-projections', ['--product' => $fixture['product']->id])->assertSuccessful();
});

it('detects orphaned projections without mutating authoritative tables', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    PublicCatalogVariantProjection::query()->create([
        'product_variant_id' => 9_999_999,
        'product_id' => 9_999_999,
        'currency_code' => 'GEL',
        'is_public' => false,
        'projected_at' => now(),
    ]);

    $sku = $fixture['variant']->sku;
    $this->artisan('catalog:verify-public-projections')->assertFailed();
    expect($fixture['variant']->fresh()?->sku)->toBe($sku);
});
