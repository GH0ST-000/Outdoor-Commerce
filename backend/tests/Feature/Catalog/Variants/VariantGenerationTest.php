<?php

declare(strict_types=1);

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Enums\Role;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

/**
 * @return array{product: Product, axes: list<array{attribute_id: int, attribute_value_ids: list<int>}>}
 */
function generationFixture(int $colorCount = 2, int $sizeCount = 3): array
{
    $product = Product::factory()->create();
    $color = Attribute::factory()->active()->color()->create();
    $size = Attribute::factory()->active()->create();

    $colorIds = [];
    for ($i = 0; $i < $colorCount; $i++) {
        $colorIds[] = (int) AttributeValue::factory()->forAttribute($color)->active()->create()->id;
    }

    $sizeIds = [];
    for ($i = 0; $i < $sizeCount; $i++) {
        $sizeIds[] = (int) AttributeValue::factory()->forAttribute($size)->active()->create()->id;
    }

    $product->variantAttributes()->sync([
        $color->id => ['sort_order' => 0],
        $size->id => ['sort_order' => 1],
    ]);

    return [
        'product' => $product,
        'axes' => [
            ['attribute_id' => (int) $color->id, 'attribute_value_ids' => $colorIds],
            ['attribute_id' => (int) $size->id, 'attribute_value_ids' => $sizeIds],
        ],
    ];
}

it('previews the cartesian plan without writing anything', function (): void {
    $admin = $this->createAdmin();
    ['product' => $product, 'axes' => $axes] = generationFixture();

    $response = $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants/generate-preview", ['axes' => $axes]);

    $response->assertOk()
        ->assertJsonPath('data.total_combinations', 6)
        ->assertJsonPath('data.new_count', 6)
        ->assertJsonPath('data.existing_count', 0)
        ->assertJsonPath('data.exceeds_limit', false);

    expect(count($response->json('data.combinations')))->toBe(6);
    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(0);
    expect(AuditLog::query()->where('event', AuditEvent::ProductVariantsGenerated->value)->exists())->toBeFalse();
});

it('generates the missing combinations, skips existing ones, and assigns sequential SKUs', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    ['product' => $product, 'axes' => $axes] = generationFixture();

    $first = $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants/generate", ['axes' => $axes]);

    $first->assertCreated()
        ->assertJsonPath('meta.summary.requested', 6)
        ->assertJsonPath('meta.summary.created', 6)
        ->assertJsonPath('meta.summary.skipped', 0);

    $skus = ProductVariant::query()->where('product_id', $product->id)->orderBy('id')->pluck('sku')->all();
    expect($skus)->toBe(array_map(
        static fn (int $i): string => 'PRD-'.$product->id.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        range(1, 6),
    ));

    // The first generated variant becomes the product default.
    expect(ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->count())->toBe(1);
    expect(AuditLog::query()->where('event', AuditEvent::ProductVariantsGenerated->value)->exists())->toBeTrue();

    $this->app['auth']->forgetGuards();

    $second = $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants/generate", ['axes' => $axes]);

    $second->assertCreated()
        ->assertJsonPath('meta.summary.created', 0)
        ->assertJsonPath('meta.summary.skipped', 6);

    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(6);
});

it('rejects a generation request above the per-call combination limit', function (): void {
    config(['catalog.variants.max_combinations_per_generation' => 4]);

    $admin = $this->createAdmin();
    ['product' => $product, 'axes' => $axes] = generationFixture(colorCount: 3, sizeCount: 3);

    $preview = $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants/generate-preview", ['axes' => $axes]);

    $preview->assertOk()
        ->assertJsonPath('data.total_combinations', 9)
        ->assertJsonPath('data.exceeds_limit', true)
        ->assertJsonPath('data.truncated', true);

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants/generate", ['axes' => $axes])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'COMBINATION_LIMIT_EXCEEDED')
        ->assertJsonPath('error.details.count', 9)
        ->assertJsonPath('error.details.limit', 4);

    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(0);
});

it('rejects generation for products without axes or with unusable values', function (): void {
    $admin = $this->createAdmin();
    $bare = Product::factory()->create();
    $attribute = Attribute::factory()->active()->create();
    $value = AttributeValue::factory()->forAttribute($attribute)->active()->create();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$bare->id}/variants/generate", [
            'axes' => [['attribute_id' => $attribute->id, 'attribute_value_ids' => [$value->id]]],
        ])
        ->assertUnprocessable();

    $this->app['auth']->forgetGuards();

    ['product' => $product, 'axes' => $axes] = generationFixture();
    $draftValue = AttributeValue::factory()->forAttribute(Attribute::query()->findOrFail($axes[0]['attribute_id']))->create();
    $axes[0]['attribute_value_ids'][] = (int) $draftValue->id;

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants/generate", ['axes' => $axes])
        ->assertUnprocessable();

    expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(0);
});
