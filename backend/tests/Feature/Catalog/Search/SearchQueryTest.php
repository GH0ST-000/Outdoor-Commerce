<?php

declare(strict_types=1);

use App\Domains\Catalog\PublicApi\Data\PublicProductListFilterData;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogContextFactory;
use App\Domains\Catalog\Search\Services\SearchIndexManager;
use App\Domains\Catalog\Search\Services\SearchQueryService;
use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use Illuminate\Http\Request;
use Tests\Support\PublicCatalogFixtures;
use Tests\Support\UsesFakeSearchGateway;

uses(UsesFakeSearchGateway::class);

function searchContext(string $locale = 'ka'): mixed
{
    $request = Request::create('/api/v1/catalog/products', 'GET', ['locale' => $locale]);

    return app(PublicCatalogContextFactory::class)->fromRequest($request);
}

it('finds exact names, prefixes, SKUs, and Georgian queries', function (): void {
    $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();
    $scope = PublicCatalogFixtures::publicProduct([
        'ka_name' => 'ოპტიკური სამიზნე',
        'en_name' => 'Alpine Scope',
        'sku' => 'PRD-SCOPE-9',
    ]);
    PublicCatalogFixtures::publicProduct(['ka_name' => 'ქურთუკი', 'en_name' => 'Jacket', 'sku' => 'PRD-JKT-1']);
    app(SearchSynchronizationService::class)->syncProduct($scope['product']->id);

    $filters = fn (string $q, string $locale = 'ka') => new PublicProductListFilterData(q: $q, page: 1, perPage: 10);
    $query = app(SearchQueryService::class);

    $exact = $query->searchProducts(searchContext('ka'), $filters('ოპტიკური სამიზნე'));
    expect($exact->productIds())->toContain($scope['product']->id);

    $prefix = $query->searchProducts(searchContext('en'), $filters('Alpi', 'en'));
    expect($prefix->productIds())->toContain($scope['product']->id);

    $sku = $query->searchProducts(searchContext('ka'), $filters('PRD-SCOPE-9'));
    expect($sku->productIds())->toContain($scope['product']->id);
});

it('deduplicates variant hits to one product and preserves order after hydration', function (): void {
    $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();
    $axis = PublicCatalogFixtures::multiAxisProduct();
    app(SearchSynchronizationService::class)->syncProduct($axis['product']->id);

    $response = $this->getJson('/api/v1/catalog/products?q=Test&locale=en')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($axis['product']->id)
        ->and(array_unique($ids))->toHaveCount(count($ids));
});

it('rejects invalid locale, sort, and oversized queries on catalog search', function (): void {
    $this->getJson('/api/v1/catalog/products?q=scope&locale=fr')
        ->assertStatus(422);
    $this->getJson('/api/v1/catalog/products?q=scope&sort=nope')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CATALOG_FILTER_INVALID');
    $this->getJson('/api/v1/catalog/products?q='.str_repeat('a', 200))
        ->assertStatus(422);
});

it('defaults search pagination to 10', function (): void {
    $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();
    for ($i = 0; $i < 12; $i++) {
        $fixture = PublicCatalogFixtures::publicProduct(['ka_name' => 'სამიზნე '.$i, 'sku' => 'PRD-PAGE-'.$i]);
        app(SearchSynchronizationService::class)->syncProduct($fixture['product']->id);
    }

    $response = $this->getJson('/api/v1/catalog/products?'.http_build_query(['q' => 'სამიზნე'], '', '&', PHP_QUERY_RFC3986))->assertOk();
    expect($response->json('meta.pagination.per_page'))->toBe(10);
    expect($response->json('data'))->toHaveCount(10);
});
