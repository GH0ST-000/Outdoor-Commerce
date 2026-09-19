<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

function attributePayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'color_'.uniqid(),
        'type' => 'select',
        'is_filterable' => true,
        'sort_order' => 0,
        'translations' => [
            ['locale' => 'ka', 'name' => 'ფერი'],
            ['locale' => 'en', 'name' => 'Color'],
        ],
    ], $overrides);
}

it('creates attributes for catalog managers and records an audit event', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);

    $response = $this->actingAs($manager, 'web')
        ->postJson('/api/v1/admin/attributes', attributePayload(['code' => 'Color Axis!']));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        // Codes are normalized to lowercase snake_case.
        ->assertJsonPath('data.code', 'color_axis');

    expect(AuditLog::query()->where('event', AuditEvent::AttributeCreated->value)->exists())->toBeTrue();
});

it('rejects unauthenticated and unauthorized attribute access', function (): void {
    $this->postJson('/api/v1/admin/attributes', attributePayload())->assertUnauthorized();

    $customer = User::factory()->create();
    $this->actingAs($customer, 'web')
        ->postJson('/api/v1/admin/attributes', attributePayload())
        ->assertForbidden();

    $this->app['auth']->forgetGuards();
    $orderManager = $this->createUserWithRole(Role::OrderManager);
    $this->actingAs($orderManager, 'web')
        ->getJson('/api/v1/admin/attributes')
        ->assertForbidden();
});

it('rejects duplicate attribute codes', function (): void {
    $admin = $this->createAdmin();

    $this->actingAs($admin, 'web')
        ->postJson('/api/v1/admin/attributes', attributePayload(['code' => 'magnification']))
        ->assertCreated();

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->postJson('/api/v1/admin/attributes', attributePayload(['code' => 'magnification']))
        ->assertUnprocessable()
        ->assertJsonPath('error.details.code.0', 'This code is already taken.');
});

it('requires a georgian translation before activation', function (): void {
    $admin = $this->createAdmin();

    $this->actingAs($admin, 'web')
        ->postJson('/api/v1/admin/attributes', attributePayload([
            'status' => 'active',
            'translations' => [['locale' => 'en', 'name' => 'Only English']],
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');

    $this->app['auth']->forgetGuards();

    $attribute = Attribute::factory()->withoutGeorgian()->create();
    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/attributes/{$attribute->id}/status", ['status' => 'active'])
        ->assertUnprocessable();

    expect($attribute->fresh()?->status)->toBe(AttributeStatus::Draft);
});

it('activates an attribute that has a georgian translation', function (): void {
    $admin = $this->createAdmin();
    $attribute = Attribute::factory()->create();

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/attributes/{$attribute->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect(AuditLog::query()->where('event', AuditEvent::AttributeStatusChanged->value)->exists())->toBeTrue();
});

it('freezes code and type once the attribute has values', function (): void {
    $admin = $this->createAdmin();
    $attribute = Attribute::factory()->active()->create();
    $attribute->values()->create(['code' => 'black', 'status' => 'draft', 'sort_order' => 0]);

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/attributes/{$attribute->id}", ['code' => 'renamed'])
        ->assertUnprocessable();

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/attributes/{$attribute->id}", ['type' => 'color'])
        ->assertUnprocessable();

    $this->app['auth']->forgetGuards();

    // Renaming an unused attribute is still allowed.
    $unused = Attribute::factory()->create();
    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/attributes/{$unused->id}", ['code' => 'renamed_ok'])
        ->assertOk()
        ->assertJsonPath('data.code', 'renamed_ok');
});

it('archives and restores attributes to draft', function (): void {
    $admin = $this->createAdmin();
    $attribute = Attribute::factory()->active()->create();

    $this->actingAs($admin, 'web')
        ->deleteJson("/api/v1/admin/attributes/{$attribute->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');

    expect(Attribute::query()->find($attribute->id))->toBeNull();
    expect(Attribute::withTrashed()->find($attribute->id))->not->toBeNull();

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/attributes/{$attribute->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');
});

it('lists attributes with default pagination of 10 and filters', function (): void {
    $admin = $this->createAdmin();
    Attribute::factory()->count(12)->create();
    Attribute::factory()->color()->active()->filterable()->create();

    $response = $this->actingAs($admin, 'web')->getJson('/api/v1/admin/attributes');

    $response->assertOk()->assertJsonPath('meta.per_page', 10);
    expect(count($response->json('data')))->toBe(10);

    $this->app['auth']->forgetGuards();

    $filtered = $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/attributes?type=color&status=active&is_filterable=1');

    $filtered->assertOk();
    expect(count($filtered->json('data')))->toBe(1);
    expect($filtered->json('data.0.value_count'))->toBe(0);
});
