<?php

declare(strict_types=1);

use App\Domains\Catalog\Search\Services\SearchIndexManager;
use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use App\Domains\Identity\Enums\Role;
use Tests\Support\InteractsWithAccessControl;
use Tests\Support\PublicCatalogFixtures;
use Tests\Support\UsesFakeSearchGateway;

uses(UsesFakeSearchGateway::class, InteractsWithAccessControl::class);

it('returns grouped search results for products categories and brands', function (): void {
    $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();
    $brand = PublicCatalogFixtures::brand(['ka_name' => 'კონდორი', 'ka_slug' => 'kondori', 'en_name' => 'Condor', 'en_slug' => 'condor']);
    $category = PublicCatalogFixtures::category(['ka_name' => 'ოპტიკა', 'ka_slug' => 'optika', 'en_name' => 'Optics', 'en_slug' => 'optics']);
    $product = PublicCatalogFixtures::publicProduct([
        'brand' => $brand,
        'category' => $category,
        'ka_name' => 'კონდორი სამიზნე',
        'en_name' => 'Condor Scope',
        'sku' => 'PRD-GRP-1',
    ]);
    $sync = app(SearchSynchronizationService::class);
    $sync->syncProduct($product['product']->id);
    $sync->syncBrand($brand->id);
    $sync->syncCategory($category->id);

    $response = $this->getJson('/api/v1/search?'.http_build_query([
        'q' => 'კონდორი',
        'locale' => 'ka',
    ], '', '&', PHP_QUERY_RFC3986))->assertOk();
    expect($response->json('data.query'))->toBe('კონდორი')
        ->and($response->json('data.products'))->not->toBeEmpty()
        ->and($response->json('meta.fallback_used'))->toBeFalse();
    expect(collect($response->json('data.products'))->pluck('id')->unique()->count())
        ->toBe(count($response->json('data.products')));
});

it('returns autocomplete suggestions after two characters', function (): void {
    $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();
    $fixture = PublicCatalogFixtures::publicProduct(['ka_name' => 'სამიზნე', 'en_name' => 'Scope', 'sku' => 'PRD-SUG-1']);
    app(SearchSynchronizationService::class)->syncProduct($fixture['product']->id);

    $this->getJson('/api/v1/search/suggestions?'.http_build_query(['q' => 's'], '', '&', PHP_QUERY_RFC3986))->assertStatus(422);
    $ok = $this->getJson('/api/v1/search/suggestions?'.http_build_query([
        'q' => 'სა',
        'locale' => 'ka',
    ], '', '&', PHP_QUERY_RFC3986))->assertOk();
    expect($ok->json('data.query'))->toBe('სა');
});

it('escapes malicious query strings in grouped search responses', function (): void {
    $this->fakeSearchGateway();
    $payload = '<script>alert(1)</script>';
    $response = $this->getJson('/api/v1/search?q='.rawurlencode($payload))->assertOk();
    expect($response->json('data.query'))->toBe($payload)
        ->and($response->getContent())->not->toContain('"search":"<script');
});

it('forbids order managers from rebuilding search indexes', function (): void {
    $this->fakeSearchGateway();
    $viewer = $this->createUserWithRole(Role::OrderManager);

    $this->actingAs($viewer, 'web')
        ->postJson('/api/v1/admin/search/rebuild')
        ->assertForbidden();
});

it('allows admins to view search status', function (): void {
    $this->fakeSearchGateway();
    $admin = $this->createAdmin();

    $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/search/status')
        ->assertOk()
        ->assertJsonPath('data.enabled', true);
});
