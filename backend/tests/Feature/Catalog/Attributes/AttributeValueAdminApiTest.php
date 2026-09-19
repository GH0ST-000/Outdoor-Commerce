<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Identity\Enums\Role;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

function valuePayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'black',
        'sort_order' => 0,
        'translations' => [
            ['locale' => 'ka', 'name' => 'შავი'],
            ['locale' => 'en', 'name' => 'Black'],
        ],
    ], $overrides);
}

it('creates values under an attribute and records an audit event', function (): void {
    $manager = $this->createUserWithRole(Role::CatalogManager);
    $attribute = Attribute::factory()->active()->create();

    $this->actingAs($manager, 'web')
        ->postJson("/api/v1/admin/attributes/{$attribute->id}/values", valuePayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.attribute_id', $attribute->id);

    expect(AuditLog::query()->where('event', AuditEvent::AttributeValueCreated->value)->exists())->toBeTrue();
});

it('rejects duplicate value codes within the same attribute but allows reuse across attributes', function (): void {
    $admin = $this->createAdmin();
    $first = Attribute::factory()->active()->create();
    $second = Attribute::factory()->active()->create();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/attributes/{$first->id}/values", valuePayload())
        ->assertCreated();

    $this->app['auth']->forgetGuards();
    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/attributes/{$first->id}/values", valuePayload())
        ->assertUnprocessable();

    $this->app['auth']->forgetGuards();
    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/attributes/{$second->id}/values", valuePayload())
        ->assertCreated();
});

it('accepts color_hex only on color attributes and normalizes it', function (): void {
    $admin = $this->createAdmin();
    $color = Attribute::factory()->color()->active()->create();
    $select = Attribute::factory()->active()->create();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/attributes/{$color->id}/values", valuePayload(['color_hex' => '#1a2b3c']))
        ->assertCreated()
        ->assertJsonPath('data.color_hex', '#1A2B3C');

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/attributes/{$color->id}/values", valuePayload(['code' => 'short', 'color_hex' => '#abc']))
        ->assertCreated()
        ->assertJsonPath('data.color_hex', '#AABBCC');

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/attributes/{$color->id}/values", valuePayload(['code' => 'bad', 'color_hex' => 'nope']))
        ->assertUnprocessable()
        ->assertJsonPath('error.details.color_hex.0', 'Use a 3- or 6-digit hex color such as #1A2B3C.');

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/attributes/{$select->id}/values", valuePayload(['color_hex' => '#1A2B3C']))
        ->assertUnprocessable()
        ->assertJsonPath('error.details.color_hex.0', 'Only color attributes accept a color value.');
});

it('requires a georgian translation before activating a value', function (): void {
    $admin = $this->createAdmin();
    $attribute = Attribute::factory()->active()->create();
    $value = AttributeValue::factory()->forAttribute($attribute)->withoutGeorgian()->create();

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/attributes/{$attribute->id}/values/{$value->id}/status", ['status' => 'active'])
        ->assertUnprocessable();

    expect($value->fresh()?->status)->toBe(AttributeValueStatus::Draft);

    $this->app['auth']->forgetGuards();

    $ready = AttributeValue::factory()->forAttribute($attribute)->create();
    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/attributes/{$attribute->id}/values/{$ready->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('returns 404 when a value does not belong to the attribute in the path', function (): void {
    $admin = $this->createAdmin();
    $owner = Attribute::factory()->active()->create();
    $other = Attribute::factory()->active()->create();
    $value = AttributeValue::factory()->forAttribute($owner)->create();

    $this->actingAs($admin, 'web')
        ->getJson("/api/v1/admin/attributes/{$other->id}/values/{$value->id}")
        ->assertNotFound();

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->patchJson("/api/v1/admin/attributes/{$other->id}/values/{$value->id}", ['sort_order' => 3])
        ->assertNotFound();
});

it('archives and restores values and lists them with default pagination of 10', function (): void {
    $admin = $this->createAdmin();
    $attribute = Attribute::factory()->active()->create();
    AttributeValue::factory()->count(12)->forAttribute($attribute)->create();
    $value = AttributeValue::factory()->forAttribute($attribute)->active()->create();

    $list = $this->actingAs($admin, 'web')
        ->getJson("/api/v1/admin/attributes/{$attribute->id}/values");
    $list->assertOk()->assertJsonPath('meta.per_page', 10);
    expect(count($list->json('data')))->toBe(10);

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->deleteJson("/api/v1/admin/attributes/{$attribute->id}/values/{$value->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');

    expect(AttributeValue::query()->find($value->id))->toBeNull();

    $this->app['auth']->forgetGuards();

    $this->actingAs($admin, 'web')
        ->postJson("/api/v1/admin/attributes/{$attribute->id}/values/{$value->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');
});
