<?php

declare(strict_types=1);

use App\Domains\Catalog\Exceptions\VariantLimitExceededException;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\Variants\SkuService;
use App\Domains\Catalog\Services\Variants\VariantGenerationService;
use Illuminate\Validation\ValidationException;

it('generates the next free sequence and skips SKUs already taken', function (): void {
    $product = Product::factory()->create();
    $service = app(SkuService::class);

    expect($service->generate($product)->value)->toBe('PRD-'.$product->id.'-001');

    ProductVariant::factory()->forProduct($product)->sku('PRD-'.$product->id.'-001')->create();
    expect($service->generate($product)->value)->toBe('PRD-'.$product->id.'-002');

    // A manually entered SKU that collides with the next sequence is stepped over.
    ProductVariant::factory()->sku('PRD-'.$product->id.'-002')->create();
    expect($service->generate($product)->value)->toBe('PRD-'.$product->id.'-003');
});

it('allocates a batch of distinct SKUs in one call', function (): void {
    $product = Product::factory()->create();

    $skus = array_map(
        static fn ($sku): string => $sku->value,
        app(SkuService::class)->generateMany($product, 3),
    );

    expect($skus)->toBe([
        'PRD-'.$product->id.'-001',
        'PRD-'.$product->id.'-002',
        'PRD-'.$product->id.'-003',
    ]);
});

it('counts soft-deleted variants when checking SKU uniqueness', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($product)->sku('archived-sku')->create();
    $variant->delete();

    expect(fn () => app(SkuService::class)->assertUnique(
        app(SkuService::class)->fromInput('archived-sku'),
    ))->toThrow(ValidationException::class);
});

it('enforces the per-product variant limit', function (): void {
    config(['catalog.variants.max_variants_per_product' => 1]);

    $product = Product::factory()->create();
    ProductVariant::factory()->forProduct($product)->create();

    expect(fn () => app(VariantGenerationService::class)->assertProductVariantCapacity($product, 1))
        ->toThrow(VariantLimitExceededException::class);

    expect((new VariantLimitExceededException(2, 1))->errorDetails())->toBe(['count' => 2, 'limit' => 1]);
});
