<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\Products\ProductReadinessService;
use App\Domains\Identity\Enums\Role;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

function readyProductWithoutVariants(): Product
{
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'primary_category_id' => $category->id,
        'status' => ProductStatus::Draft,
    ]);
    $product->categories()->sync([$category->id => ['sort_order' => 0]]);

    return $product->fresh() ?? $product;
}

it('blocks activation until the product has one active default variant', function (): void {
    $product = readyProductWithoutVariants();
    $service = app(ProductReadinessService::class);

    $without = $service->evaluate($product);
    expect($without['ready'])->toBeFalse();
    expect($without['issues'])->toHaveKeys(['variants', 'variants.default']);

    // A draft default variant satisfies "exactly one default" but not "active".
    $variant = ProductVariant::factory()->forProduct($product)->default()->create();
    $product->unsetRelation('variants');
    $draftOnly = $service->evaluate($product);
    expect($draftOnly['ready'])->toBeFalse();
    expect($draftOnly['issues'])->toHaveKeys(['variants', 'variants.default']);

    $variant->forceFill(['status' => ProductVariantStatus::Active])->save();
    $product->unsetRelation('variants');
    expect($service->evaluate($product)['ready'])->toBeTrue();
});

it('reports issues as warnings for an already-active product without demoting it', function (): void {
    $product = Product::factory()->active()->create();
    $category = Category::factory()->create();
    $product->primary_category_id = $category->id;
    $product->save();
    $product->categories()->sync([$category->id => ['sort_order' => 0]]);

    $product->variants()->each(fn (ProductVariant $variant) => $variant->delete());
    $product = $product->fresh() ?? $product;

    $result = app(ProductReadinessService::class)->evaluate($product);

    expect($result['ready'])->toBeFalse();
    expect($result['warnings'])->toHaveKey('variants');
    // evaluate() never mutates status.
    expect($product->fresh()?->status)->toBe(ProductStatus::Active);
});

it('exposes variant readiness on the product readiness endpoint', function (): void {
    $admin = $this->createAdmin();
    $product = readyProductWithoutVariants();

    $response = $this->actingAs($admin, 'web')
        ->getJson("/api/v1/admin/products/{$product->id}/readiness");

    $response->assertOk()
        ->assertJsonPath('data.ready', false)
        ->assertJsonPath('data.issues.variants.0', 'At least one active variant is required.');
});

it('activates a product once an active default variant exists', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = readyProductWithoutVariants();

    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/products/{$product->id}/status", ['status' => 'active'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'PRODUCT_NOT_READY');

    $this->app['auth']->forgetGuards();

    $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants", ['status' => 'active'])
        ->assertCreated();

    $this->app['auth']->forgetGuards();

    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/products/{$product->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});
