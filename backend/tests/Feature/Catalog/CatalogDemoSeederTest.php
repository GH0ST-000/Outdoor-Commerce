<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Database\Seeders\CatalogDemoSeeder;

it('seeds variant attributes and default variants idempotently', function (): void {
    $this->seed(CatalogDemoSeeder::class);
    $this->seed(CatalogDemoSeeder::class);

    $codes = ['color', 'size', 'length', 'magnification', 'reticle'];
    foreach ($codes as $code) {
        $attribute = Attribute::query()->where('code', $code)->first();
        expect($attribute)->not->toBeNull();
        expect($attribute?->status)->toBe(AttributeStatus::Active);
        expect($attribute?->translations()->pluck('locale')->sort()->values()->all())->toBe(['en', 'ka']);
        expect($attribute?->values()->count())->toBeGreaterThan(0);
    }

    $optic = Product::query()->where('model_number', 'DEMO-OPTIC-1')->firstOrFail();
    expect($optic->variantAttributes()->count())->toBe(2);
    // 2 magnifications x 2 reticles, created once despite seeding twice.
    expect($optic->variants()->count())->toBe(4);
    expect($optic->variants()->where('is_default', true)->count())->toBe(1);

    $knife = Product::query()->where('model_number', 'DEMO-KNIFE-1')->firstOrFail();
    expect($knife->variantAttributes()->count())->toBe(0);
    expect($knife->variants()->count())->toBe(1);

    $variants = ProductVariant::query()->get();
    expect($variants->pluck('sku')->unique()->count())->toBe($variants->count());
    expect($variants->every(fn (ProductVariant $variant): bool => $variant->status === ProductVariantStatus::Active))->toBeTrue();
    // No fake commerce data is seeded.
    expect($variants->whereNotNull('barcode'))->toBeEmpty();
});
