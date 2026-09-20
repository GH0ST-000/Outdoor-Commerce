<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\Products\ProductReadinessService;
use App\Domains\Identity\Enums\Role;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\MediaFixtures;

uses(InteractsWithAccessControl::class);

beforeEach(function (): void {
    MediaFixtures::fakeDisks();
});

function readyProduct(): Product
{
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    $product = Product::factory()->withDefaultVariant()->create([
        'brand_id' => $brand->id,
        'primary_category_id' => $category->id,
        'status' => ProductStatus::Draft,
    ]);
    $product->categories()->sync([$category->id => ['sort_order' => 0]]);

    return $product->fresh(['translations', 'categories', 'brand', 'primaryCategory', 'variants']) ?? $product;
}

it('warns about missing media without blocking activation', function (): void {
    $product = readyProduct();

    $result = app(ProductReadinessService::class)->evaluate($product);

    expect($result['ready'])->toBeTrue()
        ->and($result['issues'])->toBe([])
        ->and(array_keys($result['media_warnings']))->toBe(['media.images', 'media.primary']);
});

it('drops the image warning once a variant has a processed image', function (): void {
    $product = readyProduct();
    /** @var ProductVariant $variant */
    $variant = $product->variants->first();
    MediaAttachment::factory()->forVariant($variant)->ready()->primary()->create();

    $result = app(ProductReadinessService::class)->evaluate($product->fresh() ?? $product);

    expect($result['ready'])->toBeTrue()
        ->and($result['media_warnings'])->not->toHaveKey('media.images')
        // A variant image does not stand in for the product's own primary.
        ->and($result['media_warnings'])->toHaveKey('media.primary');
});

it('warns when the primary image has no georgian alt text', function (): void {
    $product = readyProduct();
    $attachment = MediaAttachment::factory()->forProduct($product)->ready()->primary()->create();

    $result = app(ProductReadinessService::class)->evaluate($product->fresh() ?? $product);

    expect($result['ready'])->toBeTrue()
        ->and(array_keys($result['media_warnings']))->toBe(['media.primary.alt_text.ka']);

    // English alone does not satisfy the default storefront locale.
    $attachment->translations()->create(['locale' => 'en', 'alt_text' => 'Rifle scope']);
    $result = app(ProductReadinessService::class)->evaluate($product->fresh() ?? $product);
    expect(array_keys($result['media_warnings']))->toBe(['media.primary.alt_text.ka']);

    $attachment->translations()->create(['locale' => 'ka', 'alt_text' => 'ოპტიკური სამიზნე']);
    $result = app(ProductReadinessService::class)->evaluate($product->fresh() ?? $product);
    expect($result['media_warnings'])->toBe([]);
});

it('reports media warnings through the readiness endpoint', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = readyProduct();

    $response = $this->actingAs($manager, 'web')
        ->getJson("/api/v1/admin/products/{$product->id}/readiness")
        ->assertOk()
        ->assertJsonPath('data.ready', true);

    expect($response->json('data.media_warnings'))
        ->toHaveKey('media.primary')
        ->and($response->json('data.media_warnings')['media.primary'])
        ->toBe(['This product has no processed primary image.']);
});

it('activates a product that has no media at all', function (): void {
    $manager = $this->createUserWithRole(Role::Admin);
    $product = readyProduct();

    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/products/{$product->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});
