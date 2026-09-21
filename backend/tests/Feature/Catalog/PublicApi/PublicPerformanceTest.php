<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\PublicCatalogFixtures;

it('keeps representative public catalog query counts bounded', function (): void {
    $parent = PublicCatalogFixtures::category(['ka_slug' => 'perf-hunting']);
    $brand = PublicCatalogFixtures::brand(['ka_slug' => 'perf-brand']);
    for ($i = 0; $i < 6; $i++) {
        PublicCatalogFixtures::publicProduct([
            'category' => $parent,
            'brand' => $brand,
            'price' => 10000 + ($i * 500),
        ]);
    }
    PublicCatalogFixtures::multiAxisProduct();

    $measurements = [];

    $measure = function (string $label, string $url) use (&$measurements): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $started = microtime(true);
        $response = test()->getJson($url)->assertSuccessful();
        $uncachedMs = (int) round((microtime(true) - $started) * 1000);
        $count = count(DB::getQueryLog());
        $bytes = strlen((string) $response->getContent());

        DB::flushQueryLog();
        $started = microtime(true);
        $cached = test()->getJson($url)->assertSuccessful();
        $cachedMs = (int) round((microtime(true) - $started) * 1000);
        $cachedQueries = count(DB::getQueryLog());

        $measurements[$label] = [
            'queries' => $count,
            'uncached_ms' => $uncachedMs,
            'cached_ms' => $cachedMs,
            'cached_queries' => $cachedQueries,
            'bytes' => $bytes,
        ];

        expect($cached->json())->toEqual($response->json());

        return $count;
    };

    expect($measure('category_tree', '/api/v1/catalog/categories'))->toBeLessThan(25);
    expect($measure('product_list', '/api/v1/catalog/products'))->toBeLessThan(40);
    expect($measure('product_list_filtered', '/api/v1/catalog/products?category=perf-hunting&brand[]=perf-brand'))->toBeLessThan(45);
    expect($measure('product_list_attributes', '/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => ['black']],
    ])))->toBeLessThan(50);
    expect($measure('product_list_price_sort', '/api/v1/catalog/products?sort=price_asc'))->toBeLessThan(40);
    expect($measure('product_facets', '/api/v1/catalog/products/facets'))->toBeLessThan(60);

    $slug = PublicCatalogFixtures::publicProduct(['ka_slug' => 'perf-detail'])['ka_slug'];
    expect($measure('product_detail', '/api/v1/catalog/products/'.$slug))->toBeLessThan(80);

    $logDir = storage_path('logs');
    if (! is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    file_put_contents(
        $logDir.'/public-catalog-perf.json',
        (string) json_encode($measurements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    );

    expect($measurements)->not->toBeEmpty();
});
