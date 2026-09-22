<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogProjector;
use App\Domains\Catalog\Search\Enums\SearchIndexType;
use App\Domains\Catalog\Search\Services\SearchIndexManager;
use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use App\Jobs\SyncProductSearchDocuments;
use Illuminate\Support\Facades\Bus;
use Tests\Support\PublicCatalogFixtures;
use Tests\Support\UsesFakeSearchGateway;

uses(UsesFakeSearchGateway::class);

it('hydrates away stale hits so unpublished products cannot leak', function (): void {
    $gateway = $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();
    $fixture = PublicCatalogFixtures::publicProduct([
        'ka_name' => 'ფარული სამიზნე',
        'en_name' => 'Hidden Scope',
        'sku' => 'PRD-HIDE-1',
    ]);
    app(SearchSynchronizationService::class)->syncProduct($fixture['product']->id);
    $uid = app(SearchIndexManager::class)->uid(SearchIndexType::Variants, 'ka');
    expect($gateway->documentCount($uid))->toBeGreaterThan(0);

    Bus::fake([SyncProductSearchDocuments::class]);

    $fixture['product']->update([
        'status' => ProductStatus::Draft,
        'published_at' => null,
    ]);
    app(PublicCatalogProjector::class)->refreshProductGraph($fixture['product']->id);

    expect($gateway->documentCount($uid))->toBeGreaterThan(0);

    $response = $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'q' => 'ფარული',
        'locale' => 'ka',
    ], '', '&', PHP_QUERY_RFC3986))->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->not->toContain($fixture['product']->id);
});

it('uses mysql fallback when Meilisearch is unavailable', function (): void {
    $gateway = $this->fakeSearchGateway();
    $gateway->failSearch = true;
    PublicCatalogFixtures::publicProduct(['ka_name' => 'ბინოკლი', 'sku' => 'PRD-FALL-1']);

    $response = $this->getJson('/api/v1/catalog/products?'.http_build_query(['q' => 'ბინოკლი'], '', '&', PHP_QUERY_RFC3986))->assertOk();
    expect($response->json('meta.fallback_used'))->toBeTrue()
        ->and($response->json('meta.search_mode'))->toBe('mysql_fallback')
        ->and(collect($response->json('data'))->pluck('name')->all())->toContain('ბინოკლი');
    expect($response->getContent())->not->toContain('masterKey');
});

it('returns a controlled 503 when fallback is disabled', function (): void {
    config(['search.fallback_enabled' => false]);
    $gateway = $this->fakeSearchGateway();
    $gateway->failSearch = true;
    PublicCatalogFixtures::publicProduct(['ka_name' => 'ბინოკლი']);

    $this->getJson('/api/v1/catalog/products?'.http_build_query(['q' => 'ბინოკლი'], '', '&', PHP_QUERY_RFC3986))
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'SEARCH_UNAVAILABLE');
});

it('keeps category browsing working during a search outage', function (): void {
    $gateway = $this->fakeSearchGateway();
    $gateway->failSearch = true;
    $gateway->healthy = false;
    PublicCatalogFixtures::publicProduct(['ka_name' => 'კატალოგი']);

    $this->getJson('/api/v1/catalog/products')->assertOk()
        ->assertJsonPath('meta.search_mode', 'basic_mysql');
    $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
});
