<?php

declare(strict_types=1);

use App\Domains\Catalog\DTOs\CatalogSellableRefData;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Models\PromotionTarget;
use App\Domains\Pricing\Services\PromotionEligibilityService;
use Tests\Support\PricingFixtures;

function sellableRefFrom(ProductVariant $variant, Product $product): CatalogSellableRefData
{
    $product->loadMissing(['categories', 'brand']);

    return new CatalogSellableRefData(
        variantId: $variant->id,
        productId: $product->id,
        brandId: $product->brand_id,
        isDefault: $variant->is_default,
        variantActive: true,
        variantDeleted: false,
        productDeleted: false,
        brandDeleted: false,
        categoryIds: $product->categories->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all(),
    );
}

it('matches product inclusion and excludes when product is excluded', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->active()->for($product)->create();
    PricingFixtures::publishedPrice($variant, 10_000);
    $sellable = sellableRefFrom($variant, $product);

    $promo = Promotion::factory()->active()->create();
    PromotionTarget::query()->create([
        'promotion_id' => $promo->id,
        'target_type' => PromotionTargetType::Product,
        'target_id' => $product->id,
        'mode' => PromotionTargetMode::Include,
    ]);

    $eligibility = app(PromotionEligibilityService::class);
    expect($eligibility->isEligible($promo->fresh('targets'), $sellable, 'GEL'))->toBeTrue();

    PromotionTarget::query()->create([
        'promotion_id' => $promo->id,
        'target_type' => PromotionTargetType::Product,
        'target_id' => $product->id,
        'mode' => PromotionTargetMode::Exclude,
    ]);

    expect($eligibility->isEligible($promo->fresh('targets'), $sellable, 'GEL'))->toBeFalse();
});

it('matches category by direct assignment only', function (): void {
    $category = Category::factory()->create();
    $other = Category::factory()->create();
    $product = Product::factory()->create(['primary_category_id' => null]);
    $product->categories()->attach($category->id);
    $product->primary_category_id = $category->id;
    $product->save();
    $variant = ProductVariant::factory()->active()->for($product)->create();
    $sellable = sellableRefFrom($variant, $product->fresh('categories') ?? $product);

    $promo = Promotion::factory()->active()->create();
    PromotionTarget::query()->create([
        'promotion_id' => $promo->id,
        'target_type' => PromotionTargetType::Category,
        'target_id' => $category->id,
        'mode' => PromotionTargetMode::Include,
    ]);

    $eligibility = app(PromotionEligibilityService::class);
    expect($eligibility->isEligible($promo->fresh('targets'), $sellable, 'GEL'))->toBeTrue();

    $promoOther = Promotion::factory()->active()->create(['code' => 'other-cat']);
    PromotionTarget::query()->create([
        'promotion_id' => $promoOther->id,
        'target_type' => PromotionTargetType::Category,
        'target_id' => $other->id,
        'mode' => PromotionTargetMode::Include,
    ]);

    expect($eligibility->isEligible($promoOther->fresh('targets'), $sellable, 'GEL'))->toBeFalse();
});

it('matches brand targets and rejects empty target sets', function (): void {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);
    $variant = ProductVariant::factory()->active()->for($product)->create();
    $sellable = sellableRefFrom($variant, $product->load('brand'));

    $promo = Promotion::factory()->active()->create();
    expect(app(PromotionEligibilityService::class)->isEligible($promo, $sellable, 'GEL'))->toBeFalse();

    PromotionTarget::query()->create([
        'promotion_id' => $promo->id,
        'target_type' => PromotionTargetType::Brand,
        'target_id' => $brand->id,
        'mode' => PromotionTargetMode::Include,
    ]);

    expect(app(PromotionEligibilityService::class)->isEligible($promo->fresh('targets'), $sellable, 'GEL'))->toBeTrue();
});
