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

it('syncs ordered variant axes and records an audit event', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $product = Product::factory()->create();
    $color = Attribute::factory()->active()->color()->create();
    $size = Attribute::factory()->active()->create();

    $response = $this->actingAs($manager, 'web')->putJson(
        "/api/v1/admin/products/{$product->id}/variant-axes",
        ['axes' => [
            ['attribute_id' => $size->id, 'sort_order' => 1],
            ['attribute_id' => $color->id, 'sort_order' => 0],
        ]],
    );

    $response->assertOk()
        ->assertJsonPath('data.axes.0.attribute_id', $color->id)
        ->assertJsonPath('data.axes.1.attribute_id', $size->id);

    expect(AuditLog::query()->where('event', AuditEvent::ProductVariantAxesUpdated->value)->exists())->toBeTrue();

    $this->app['auth']->forgetGuards();

    // Clearing the axes is allowed while no variant uses them.
    $this->actingAs($manager, 'web')
        ->putJson("/api/v1/admin/products/{$product->id}/variant-axes", ['axes' => []])
        ->assertOk()
        ->assertJsonPath('data.axes', []);
});

it('rejects non-active attributes as axes', function (): void {
    $admin = $this->createAdmin();
    $product = Product::factory()->create();
    $draft = Attribute::factory()->create();

    $this->actingAs($admin, 'web')
        ->putJson("/api/v1/admin/products/{$product->id}/variant-axes", [
            'axes' => [['attribute_id' => $draft->id]],
        ])
        ->assertUnprocessable();
});

it('refuses to remove an axis that non-archived variants still use', function (): void {
    $admin = $this->createAdmin();
    $product = Product::factory()->create();
    $color = Attribute::factory()->active()->color()->create();
    $size = Attribute::factory()->active()->create();
    $colorValue = AttributeValue::factory()->forAttribute($color)->active()->create();
    $sizeValue = AttributeValue::factory()->forAttribute($size)->active()->create();

    $product->variantAttributes()->sync([
        $color->id => ['sort_order' => 0],
        $size->id => ['sort_order' => 1],
    ]);

    $variant = ProductVariant::factory()->forProduct($product)->active()->default()
        ->withValues([$colorValue, $sizeValue])->create();

    $conflict = $this->actingAs($admin, 'web')->putJson(
        "/api/v1/admin/products/{$product->id}/variant-axes",
        ['axes' => [['attribute_id' => $color->id, 'sort_order' => 0]]],
    );

    $conflict->assertUnprocessable()
        ->assertJsonPath('error.code', 'VARIANT_AXIS_CONFLICT')
        ->assertJsonPath('error.details.attribute_ids.0', $size->id)
        ->assertJsonPath('error.details.variant_ids.0', $variant->id);

    expect($product->fresh()?->variantAttributes()->count())->toBe(2);
});

it('refuses to add an axis while active variants exist', function (): void {
    $admin = $this->createAdmin();
    $product = Product::factory()->create();
    $color = Attribute::factory()->active()->color()->create();
    ProductVariant::factory()->forProduct($product)->active()->default()->create();

    $this->actingAs($admin, 'web')
        ->putJson("/api/v1/admin/products/{$product->id}/variant-axes", [
            'axes' => [['attribute_id' => $color->id]],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VARIANT_AXIS_CONFLICT');
});
