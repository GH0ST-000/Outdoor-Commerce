<?php

declare(strict_types=1);

use App\Domains\Catalog\Services\CatalogCache;
use Tests\Support\PublicCatalogFixtures;

it('emits etag cache-control content-language and 304 without a body', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['ka_slug' => 'etag-product']);

    $first = $this->getJson('/api/v1/catalog/products/etag-product?locale=ka')->assertOk();
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeEmpty();
    expect($first->headers->get('Cache-Control'))->toContain('max-age');
    expect($first->headers->get('Content-Language'))->toBe('ka');
    expect($first->headers->get('Vary'))->toContain('Accept-Language');

    $notModified = $this->withHeaders(['If-None-Match' => $etag])
        ->getJson('/api/v1/catalog/products/etag-product?locale=ka');
    $notModified->assertStatus(304);
    expect($notModified->getContent())->toBe('');

    $en = $this->getJson('/api/v1/catalog/products/etag-product?locale=en')->assertOk();
    expect($en->headers->get('ETag'))->not->toBe($etag);

    $fixture['product']->update(['is_featured' => true]);
    PublicCatalogFixtures::rebuild($fixture['product']->id);
    app(CatalogCache::class)->bump();
    $changed = $this->getJson('/api/v1/catalog/products/etag-product?locale=ka')->assertOk();
    expect($changed->headers->get('ETag'))->not->toBe($etag);
});

it('does not spend the search budget on ordinary product list requests', function (): void {
    PublicCatalogFixtures::publicProduct(['ka_name' => 'Searchable optic', 'sku' => 'PRD-SEARCH-1']);
    $this->flushApplicationCacheSafely();
    config(['catalog.public.rate_limits.search_per_minute' => 2]);

    $this->getJson('/api/v1/catalog/products')->assertOk();
    $this->getJson('/api/v1/catalog/products')->assertOk();
    $this->getJson('/api/v1/catalog/products')->assertOk();

    $this->getJson('/api/v1/catalog/products?q=PRD-SEARCH-1')->assertOk();
    $this->getJson('/api/v1/catalog/products?q=PRD-SEARCH-1')->assertOk();
    $this->getJson('/api/v1/catalog/products?q=PRD-SEARCH-1')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'CATALOG_RATE_LIMITED');
});

it('rate limits excessive public catalog browsing and returns standard headers', function (): void {
    PublicCatalogFixtures::priceList();
    $this->flushApplicationCacheSafely();
    config(['catalog.public.rate_limits.browse_per_minute' => 3]);

    $this->getJson('/api/v1/catalog/categories')->assertOk();
    $this->getJson('/api/v1/catalog/categories')->assertOk();
    $this->getJson('/api/v1/catalog/categories')->assertOk();
    $limited = $this->getJson('/api/v1/catalog/categories');
    $limited->assertStatus(429)
        ->assertJsonPath('error.code', 'CATALOG_RATE_LIMITED');
    expect(
        $limited->headers->get('Retry-After')
        ?? $limited->headers->get('X-RateLimit-Limit')
        ?? $limited->headers->get('RateLimit-Limit')
        ?? $limited->headers->get('X-RateLimit-Remaining')
    )->not->toBeEmpty();
});
