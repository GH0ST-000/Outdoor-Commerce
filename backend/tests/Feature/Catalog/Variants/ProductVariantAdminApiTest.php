<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

/**
 * Builds a product with `color` and `size` axes plus active values for each.
 *
 * @return array{product: Product, color: Attribute, size: Attribute, colors: list<AttributeValue>, sizes: list<AttributeValue>}
 */
function twoAxisProduct(): array
{
    $product = Product::factory()->create();
    $color = Attribute::factory()->active()->color()->create();
    $size = Attribute::factory()->active()->create();

    $colors = [
        AttributeValue::factory()->forAttribute($color)->active()->withColor('#000000')->create(),
        AttributeValue::factory()->forAttribute($color)->active()->withColor('#FFFFFF')->create(),
    ];
    $sizes = [
        AttributeValue::factory()->forAttribute($size)->active()->create(),
        AttributeValue::factory()->forAttribute($size)->active()->create(),
    ];

    $product->variantAttributes()->sync([
        $color->id => ['sort_order' => 0],
        $size->id => ['sort_order' => 1],
    ]);

    return compact('product', 'color', 'size', 'colors', 'sizes');
}

it('creates the single empty combination for a zero-axis product and rejects a duplicate', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();

    $created = $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants", ['status' => 'active']);

    $created->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_default', true)
        ->assertJsonPath('data.combination_signature', '');

    // The first variant is auto-defaulted and the SKU is generated.
    expect($created->json('data.sku'))->toBe('PRD-'.$product->id.'-001');
    expect(AuditLog::query()->where('event', AuditEvent::ProductVariantCreated->value)->exists())->toBeTrue();

    $this->app['auth']->forgetGuards();

    $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants", [])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects unauthenticated and unauthorized variant writes', function (): void {
    $product = Product::factory()->create();

    $this->postJson("/api/v1/admin/products/{$product->id}/variants", [])->assertUnauthorized();

    $customer = User::factory()->create();
    $this->actingAs($customer, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants", [])
        ->assertForbidden();
});

it('treats combinations as order independent and rejects duplicates', function (): void {
    $admin = $this->createAdmin();
    ['product' => $product, 'color' => $color, 'size' => $size, 'colors' => $colors, 'sizes' => $sizes] = twoAxisProduct();

    $first = $this->actingAs($admin, 'web')->postJson("/api/v1/admin/products/{$product->id}/variants", [
        'status' => 'active',
        'attribute_values' => [
            ['attribute_id' => $color->id, 'attribute_value_id' => $colors[0]->id],
            ['attribute_id' => $size->id, 'attribute_value_id' => $sizes[0]->id],
        ],
    ])->assertCreated();

    $this->app['auth']->forgetGuards();

    // Same pair set, reversed order: identical identity, so it must be rejected.
    $duplicate = $this->actingAs($admin, 'web')->postJson("/api/v1/admin/products/{$product->id}/variants", [
        'attribute_values' => [
            ['attribute_id' => $size->id, 'attribute_value_id' => $sizes[0]->id],
            ['attribute_id' => $color->id, 'attribute_value_id' => $colors[0]->id],
        ],
    ]);

    $duplicate->assertUnprocessable();
    expect($duplicate->json('error.details.attribute_values.0'))
        ->toContain('already used by variant ['.$first->json('data.id').']');
});

it('requires one value per axis for active variants and validates ownership', function (): void {
    $admin = $this->createAdmin();
    ['product' => $product, 'color' => $color, 'size' => $size, 'colors' => $colors] = twoAxisProduct();
    $foreign = AttributeValue::factory()->active()->create();

    // Missing the size axis.
    $this->actingAs($admin, 'web')->postJson("/api/v1/admin/products/{$product->id}/variants", [
        'status' => 'active',
        'attribute_values' => [
            ['attribute_id' => $color->id, 'attribute_value_id' => $colors[0]->id],
        ],
    ])->assertUnprocessable();

    $this->app['auth']->forgetGuards();

    // Value belongs to a different attribute.
    $this->actingAs($admin, 'web')->postJson("/api/v1/admin/products/{$product->id}/variants", [
        'attribute_values' => [
            ['attribute_id' => $color->id, 'attribute_value_id' => $foreign->id],
            ['attribute_id' => $size->id, 'attribute_value_id' => $colors[0]->id],
        ],
    ])->assertUnprocessable();

    $this->app['auth']->forgetGuards();

    // Attribute is not an axis of this product.
    $this->actingAs($admin, 'web')->postJson("/api/v1/admin/products/{$product->id}/variants", [
        'attribute_values' => [
            ['attribute_id' => $foreign->attribute_id, 'attribute_value_id' => $foreign->id],
        ],
    ])->assertUnprocessable();
});

it('rejects duplicate SKUs and invalid barcodes but accepts a valid EAN-13', function (): void {
    $admin = $this->createAdmin();
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    ProductVariant::factory()->forProduct($other)->sku('taken-sku')->create();

    $this->actingAs($admin, 'web')->postJson("/api/v1/admin/products/{$product->id}/variants", [
        'sku' => 'Taken-SKU',
    ])->assertUnprocessable()->assertJsonPath('error.details.sku.0', 'This SKU is already in use.');

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')->postJson("/api/v1/admin/products/{$product->id}/variants", [
        'barcode' => '4006381333932',
    ])->assertUnprocessable()->assertJsonPath('error.details.barcode.0', 'Barcode check digit is invalid.');

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')->postJson("/api/v1/admin/products/{$product->id}/variants", [
        'sku' => 'my-sku-1',
        'barcode' => '4006381333931',
    ])->assertCreated()
        ->assertJsonPath('data.sku', 'MY-SKU-1')
        ->assertJsonPath('data.barcode', '4006381333931');
});

it('switches the default variant and never leaves more than one', function (): void {
    $admin = $this->createAdmin();
    ['product' => $product, 'color' => $color, 'size' => $size, 'colors' => $colors, 'sizes' => $sizes] = twoAxisProduct();

    $first = ProductVariant::factory()->forProduct($product)->active()->default()
        ->withValues([$colors[0], $sizes[0]])->create();
    $second = ProductVariant::factory()->forProduct($product)->active()
        ->withValues([$colors[1], $sizes[1]])->create();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants/{$second->id}/default")
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect($first->fresh()?->is_default)->toBeFalse();
    expect(ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->count())->toBe(1);
    expect(AuditLog::query()->where('event', AuditEvent::ProductVariantDefaultChanged->value)->exists())->toBeTrue();
});

it('requires a replacement when archiving the default variant', function (): void {
    $admin = $this->createAdmin();
    ['product' => $product, 'colors' => $colors, 'sizes' => $sizes] = twoAxisProduct();

    $default = ProductVariant::factory()->forProduct($product)->active()->default()
        ->withValues([$colors[0], $sizes[0]])->create();
    $other = ProductVariant::factory()->forProduct($product)->active()
        ->withValues([$colors[1], $sizes[1]])->create();

    $this->actingAs($admin, 'web')
        ->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$default->id}")
        ->assertUnprocessable()
        ->assertJsonPath('error.details.replacement_variant_id.0', 'A replacement variant is required when archiving the default variant.');

    expect($default->fresh()?->status)->toBe(ProductVariantStatus::Active);

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$default->id}", [
            'replacement_variant_id' => $other->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'archived')
        ->assertJsonPath('data.is_default', false);

    expect($other->fresh()?->is_default)->toBeTrue();
    expect(ProductVariant::query()->find($default->id))->toBeNull();
});

it('archives the last variant without a replacement and restores it as a non-active default', function (): void {
    $admin = $this->createAdmin();
    $product = Product::factory()->create();
    $only = ProductVariant::factory()->forProduct($product)->active()->default()->create();

    $this->actingAs($admin, 'web')
        ->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$only->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants/{$only->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        // Restoring re-establishes the single default because the product had none.
        ->assertJsonPath('data.is_default', true);
});

it('refuses to make an archived variant the default', function (): void {
    $admin = $this->createAdmin();
    ['product' => $product, 'colors' => $colors, 'sizes' => $sizes] = twoAxisProduct();

    $default = ProductVariant::factory()->forProduct($product)->active()->default()
        ->withValues([$colors[0], $sizes[0]])->create();
    $archived = ProductVariant::factory()->forProduct($product)->archived()
        ->withValues([$colors[1], $sizes[1]])->create();
    $archived->delete();

    // Soft-deleted variants are not resolvable through implicit binding.
    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$product->id}/variants/{$archived->id}/default")
        ->assertNotFound();

    expect($default->fresh()?->is_default)->toBeTrue();

    $this->app['auth']->forgetGuards();

    // An archived value also blocks activating a variant that still references it.
    $archivedValue = $colors[1];
    $archivedValue->forceFill(['status' => 'archived'])->save();

    $draft = ProductVariant::factory()->forProduct($product)->sku('draft-one')
        ->withValues([$colors[1], $sizes[0]])->create();

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/products/{$product->id}/variants/{$draft->id}/status", ['status' => 'active'])
        ->assertUnprocessable();
});

it('returns 404 when the variant does not belong to the product in the path', function (): void {
    $admin = $this->createAdmin();
    $owner = Product::factory()->create();
    $other = Product::factory()->create();
    $variant = ProductVariant::factory()->forProduct($owner)->create();

    $this->actingAs($admin, 'web')
        ->getJson("/api/v1/admin/products/{$other->id}/variants/{$variant->id}")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/products/{$other->id}/variants/{$variant->id}", ['sort_order' => 2])
        ->assertNotFound();
});

it('lists variants with default pagination of 10 and filters by sku and attribute', function (): void {
    $admin = $this->createAdmin();
    ['product' => $product, 'color' => $color, 'colors' => $colors, 'sizes' => $sizes] = twoAxisProduct();

    ProductVariant::factory()->count(12)->forProduct($product)->sequence(
        ...array_map(static fn (int $i): array => ['sku' => 'BULK-'.$i, 'combination_hash' => hash('sha256', 'bulk-'.$i)], range(1, 12)),
    )->create();

    ProductVariant::factory()->forProduct($product)->active()->default()->sku('match-me')
        ->withValues([$colors[0], $sizes[0]])->create();

    $list = $this->actingAs($admin, 'web')->getJson("/api/v1/admin/products/{$product->id}/variants");
    $list->assertOk()->assertJsonPath('meta.per_page', 10);
    expect(count($list->json('data')))->toBe(10);

    $this->app['auth']->forgetGuards();

    $filtered = $this->actingAs($admin, 'web')->getJson(
        "/api/v1/admin/products/{$product->id}/variants?search=match&is_default=1&attribute_id[]={$color->id}",
    );
    $filtered->assertOk();
    expect(count($filtered->json('data')))->toBe(1);
    expect($filtered->json('data.0.sku'))->toBe('MATCH-ME');
});
