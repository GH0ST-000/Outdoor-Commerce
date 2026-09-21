<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use App\Domains\Pricing\Models\PriceList;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\PricingFixtures;

uses(InteractsWithAccessControl::class);

it('creates a price list and audits the event', function (): void {
    $manager = $this->createUserWithRole(Role::PricingManager);

    $this->actingAs($manager, 'web')
        ->postJson('/api/v1/admin/price-lists', [
            'code' => 'retail-usd',
            'name' => 'Retail USD',
            'currency_code' => 'USD',
            'status' => 'active',
            'is_default' => true,
            'priority' => 1,
            'prices_include_tax' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'retail-usd')
        ->assertJsonPath('data.is_default', true);

    expect(AuditLog::query()->where('event', AuditEvent::PriceListCreated->value)->exists())->toBeTrue();
});

it('lists price lists with default pagination of 10', function (): void {
    $viewer = $this->createUserWithRole(Role::CatalogManager);
    PriceList::factory()->count(11)->create();

    $response = $this->actingAs($viewer, 'web')->getJson('/api/v1/admin/price-lists');
    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(10);
    expect($response->json('data'))->toHaveCount(10);
});

it('denies catalog manager from publishing price list status', function (): void {
    $catalog = $this->createUserWithRole(Role::CatalogManager);
    $list = PricingFixtures::retailGelList();

    $this->actingAs($catalog, 'web')
        ->patchJson("/api/v1/admin/price-lists/{$list->id}/status", ['status' => 'archived'])
        ->assertForbidden();
});

it('switches default price list transactionally', function (): void {
    $manager = $this->createUserWithRole(Role::PricingManager);
    $a = PricingFixtures::retailGelList(['code' => 'retail_gel']);
    $b = PriceList::factory()->create([
        'code' => 'wholesale_gel',
        'currency_code' => 'GEL',
        'status' => 'active',
        'is_default' => false,
    ]);

    $this->actingAs($manager, 'web')
        ->patchJson("/api/v1/admin/price-lists/{$b->id}/default")
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect($a->fresh()->is_default)->toBeFalse();
    expect($b->fresh()->is_default)->toBeTrue();
});
