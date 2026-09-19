<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\Products\ProductReadinessService;
use App\Domains\Catalog\Support\CatalogSlug;

it('validates slug unicode and readiness rules', function (): void {
    expect(CatalogSlug::isValid(CatalogSlug::normalize('ოპტიკა-100')))->toBeTrue();
    expect(CatalogSlug::isValid(''))->toBeFalse();

    $product = Product::factory()->create([
        'status' => ProductStatus::Draft,
        'primary_category_id' => null,
    ]);

    $result = app(ProductReadinessService::class)->evaluate($product);
    expect($result['ready'])->toBeFalse();
    expect($result['issue_count'])->toBeGreaterThan(0);

    $category = Category::factory()->create();
    $brand = Brand::factory()->create();
    // Activation now also requires one active default variant (Day 8).
    $ready = Product::factory()->withDefaultVariant()->create([
        'brand_id' => $brand->id,
        'primary_category_id' => $category->id,
        'status' => ProductStatus::Draft,
    ]);
    $ready->categories()->sync([$category->id => ['sort_order' => 0]]);
    $ready->load(['translations', 'categories', 'brand', 'primaryCategory']);

    $eval = app(ProductReadinessService::class)->evaluate($ready);
    expect($eval['ready'])->toBeTrue();
});
