<?php

declare(strict_types=1);

use App\Domains\Catalog\Search\Contracts\SearchGateway;
use App\Domains\Catalog\Search\Services\MeilisearchGateway;
use App\Domains\Catalog\Search\Services\SearchIndexManager;
use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use Tests\Support\PublicCatalogFixtures;

beforeEach(function (): void {
    $this->app->forgetInstance(SearchGateway::class);
    $this->app->singleton(SearchGateway::class, MeilisearchGateway::class);
});

it('configures Meilisearch indexes and searches a Georgian product when the engine is available', function (): void {
    $gateway = app(SearchGateway::class);
    if (! $gateway->health()) {
        test()->markTestSkipped('Meilisearch is not running.');
    }

    config(['search.index_prefix' => 'outdoor_test_it']);
    app(SearchIndexManager::class)->configure('ka');
    $fixture = PublicCatalogFixtures::publicProduct([
        'ka_name' => 'ინტეგრაციის სამიზნე',
        'sku' => 'PRD-MEILI-1',
    ]);
    app(SearchSynchronizationService::class)->syncProduct($fixture['product']->id);

    $this->getJson('/api/v1/catalog/products?'.http_build_query(['q' => 'ინტეგრაციის', 'locale' => 'ka'], '', '&', PHP_QUERY_RFC3986))
        ->assertOk()
        ->assertJsonPath('meta.search_mode', 'meilisearch')
        ->assertJsonPath('data.0.id', $fixture['product']->id);
});
