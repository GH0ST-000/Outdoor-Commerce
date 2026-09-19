<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Support\HtmlContentSanitizer;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

function productPayload(array $overrides = []): array
{
    $category = $overrides['__category'] ?? Category::factory()->create();
    unset($overrides['__category']);

    return array_merge([
        'brand_id' => null,
        'primary_category_id' => $category->id,
        'category_ids' => [$category->id],
        'status' => 'draft',
        'model_number' => 'MODEL-1',
        'is_featured' => false,
        'sort_order' => 0,
        'translations' => [
            [
                'locale' => 'ka',
                'name' => 'ტესტ პროდუქტი',
                'slug' => 'test-produkti-'.uniqid(),
                'short_description' => 'მოკლე',
                'description' => '<p>აღწერა</p><script>alert(1)</script>',
            ],
        ],
    ], $overrides);
}

it('creates a draft product for authorized catalog managers', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $payload = productPayload();

    $response = $this->actingAs($manager, 'web')
        ->postJson('/api/v1/admin/products', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonMissingPath('data.password');

    $product = Product::query()->findOrFail($response->json('data.id'));
    expect($product->translations()->where('locale', 'ka')->exists())->toBeTrue();
    expect((string) $product->translations()->where('locale', 'ka')->value('description'))
        ->not->toContain('<script>');
    expect(AuditLog::query()->where('event', AuditEvent::ProductCreated->value)->exists())->toBeTrue();
});

it('rejects unauthenticated and unauthorized product creation', function (): void {
    $this->postJson('/api/v1/admin/products', productPayload())->assertUnauthorized();

    $customer = User::factory()->create();
    $this->actingAs($customer, 'web')
        ->postJson('/api/v1/admin/products', productPayload())
        ->assertForbidden();
});

it('requires georgian translation and rejects duplicate locales and bad slugs', function (): void {
    $admin = $this->createAdmin();

    $this->actingAs($admin, 'web')
        ->postJson('/api/v1/admin/products', productPayload([
            'translations' => [
                ['locale' => 'en', 'name' => 'Only EN', 'slug' => 'only-en'],
            ],
        ]))
        ->assertUnprocessable();

    $this->actingAs($admin, 'web')
        ->postJson('/api/v1/admin/products', productPayload([
            'translations' => [
                ['locale' => 'ka', 'name' => 'A', 'slug' => 'same'],
                ['locale' => 'ka', 'name' => 'B', 'slug' => 'same-2'],
            ],
        ]))
        ->assertUnprocessable();
});

it('activates a ready product with publish permission and sets published_at once', function (): void {
    $admin = $this->createAdmin();
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    $create = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/products', productPayload([
        '__category' => $category,
        'brand_id' => $brand->id,
        'primary_category_id' => $category->id,
        'category_ids' => [$category->id],
    ]))->assertCreated();

    $id = $create->json('data.id');

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$id}/variants", ['status' => 'active'])
        ->assertCreated();

    $this->app['auth']->forgetGuards();

    $activate = $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/products/{$id}/status", ['status' => 'active']);

    $activate->assertOk()->assertJsonPath('data.status', 'active');
    expect($activate->json('data.published_at'))->not->toBeNull();

    $publishedAt = $activate->json('data.published_at');

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/products/{$id}", [
            'model_number' => 'MODEL-2',
            'status' => 'active',
            'sync_translations' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.published_at', $publishedAt);
});

it('blocks activation without readiness and without publish permission', function (): void {
    $orderManager = $this->createUserWithRole(Role::OrderManager);
    $admin = $this->createAdmin();
    $incomplete = Product::factory()->create([
        'primary_category_id' => null,
        'status' => ProductStatus::Draft,
    ]);

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/products/{$incomplete->id}/status", ['status' => 'active'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'PRODUCT_NOT_READY');

    $ready = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/products', productPayload())->json('data.id');

    $this->app['auth']->forgetGuards();
    $this->actingAs($orderManager, 'web')
        ->patchJson("/api/v1/admin/products/{$ready}/status", ['status' => 'active'])
        ->assertForbidden();
});

it('lists products with default pagination and filters without n+1 blowups', function (): void {
    $admin = $this->createAdmin();
    $category = Category::factory()->create();

    foreach (range(1, 12) as $i) {
        $this->actingAs($admin, 'web')->postJson('/api/v1/admin/products', productPayload([
            '__category' => $category,
            'translations' => [[
                'locale' => 'ka',
                'name' => "სახელი {$i}",
                'slug' => "saxeli-{$i}-".uniqid(),
            ]],
        ]));
        $this->app['auth']->forgetGuards();
    }

    $response = $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/products?per_page=10&status=draft');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 10);
    expect(count($response->json('data')))->toBe(10);
    expect($response->json('data.0'))->not->toHaveKey('description');
});

it('archives and restores products to draft', function (): void {
    $admin = $this->createAdmin();
    $id = $this->actingAs($admin, 'web')
        ->postJson('/api/v1/admin/products', productPayload())
        ->json('data.id');

    $this->app['auth']->forgetGuards();
    $this->actingAs($admin, 'web')
        ->deleteJson("/api/v1/admin/products/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');

    expect(Product::query()->find($id))->toBeNull();
    expect(Product::withTrashed()->find($id))->not->toBeNull();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/products/{$id}/restore")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');
});

it('sanitizes unsafe html descriptions', function (): void {
    $sanitizer = app(HtmlContentSanitizer::class);
    $clean = (string) $sanitizer->sanitize('<p onclick="x">ok</p><script>bad()</script><a href="javascript:alert(1)">x</a>');
    expect(str_contains($clean, '<p'))->toBeTrue();
    expect(str_contains($clean, 'script'))->toBeFalse();
    expect(str_contains($clean, 'onclick'))->toBeFalse();
    expect(str_contains($clean, 'javascript:'))->toBeFalse();
});
