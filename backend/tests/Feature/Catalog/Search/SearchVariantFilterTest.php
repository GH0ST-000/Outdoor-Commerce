<?php

declare(strict_types=1);

use App\Domains\Catalog\Search\Services\SearchIndexManager;
use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use Tests\Support\PublicCatalogFixtures;
use Tests\Support\UsesFakeSearchGateway;

uses(UsesFakeSearchGateway::class);

it('matches black XL on the same variant and rejects black L split across variants', function (): void {
    $this->fakeSearchGateway();
    app(SearchIndexManager::class)->configure();
    $axis = PublicCatalogFixtures::multiAxisProduct();
    app(SearchSynchronizationService::class)->syncProduct($axis['product']->id);

    $valid = $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'q' => 'Test',
        'locale' => 'en',
        'attribute' => ['color' => ['black'], 'size' => ['xl']],
    ]))->assertOk();
    expect(collect($valid->json('data'))->pluck('id')->all())->toContain($axis['product']->id);

    $invalid = $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'q' => 'Test',
        'locale' => 'en',
        'attribute' => ['color' => ['black'], 'size' => ['l']],
    ]))->assertOk();
    expect(collect($invalid->json('data'))->pluck('id')->all())->not->toContain($axis['product']->id);

    $eitherColor = $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'q' => 'Test',
        'locale' => 'en',
        'attribute' => ['color' => ['black', 'forest_green']],
    ]))->assertOk();
    expect(collect($eitherColor->json('data'))->pluck('id')->all())->toContain($axis['product']->id);
});
