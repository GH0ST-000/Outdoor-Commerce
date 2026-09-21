<?php

declare(strict_types=1);

use Tests\Support\PublicCatalogFixtures;

it('returns the documented public product list contract', function (): void {
    PublicCatalogFixtures::publicProduct();

    $response = $this->getJson('/api/v1/catalog/products')->assertOk();
    $card = $response->json('data.0');

    expect($card)->toHaveKeys([
        'id', 'name', 'slug', 'href', 'brand', 'primary_category',
        'primary_media', 'price', 'availability', 'is_featured', 'variant_count',
    ]);
    expect($card['price'])->toHaveKeys([
        'currency', 'min_final_amount_minor', 'max_final_amount_minor', 'is_range', 'on_sale',
    ]);
    expect($card['availability'])->toHaveKeys(['status', 'purchasable', 'low_stock']);
    expect($card['availability']['status'])->toBeIn(['in_stock', 'low_stock', 'out_of_stock', 'unavailable']);
    expect($response->json('meta'))->toHaveKeys(['pagination', 'filters', 'sort', 'locale', 'currency']);
    expect($response->json('meta.pagination'))->toHaveKeys(['current_page', 'per_page', 'total', 'last_page']);
    expect($response->json('links'))->toHaveKeys(['next', 'prev']);
});

it('returns the documented product detail variant matrix and money shapes', function (): void {
    $multi = PublicCatalogFixtures::multiAxisProduct();
    $response = $this->getJson('/api/v1/catalog/products/'.$multi['ka_slug'])->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveKeys([
        'id', 'name', 'slug', 'short_description', 'description', 'seo',
        'brand', 'primary_category', 'categories', 'breadcrumbs', 'gallery',
        'price', 'availability', 'variants', 'default_variant_id',
        'canonical_path', 'alternate_locale_paths',
    ]);
    expect($data['seo'])->toHaveKeys([
        'title', 'description', 'canonical_path', 'alternate_locale_paths', 'open_graph_media', 'robots',
    ]);
    expect($data['variants'])->toHaveKeys(['axes', 'combinations', 'default_variant_id']);
    $combination = $data['variants']['combinations'][0];
    expect($combination)->toHaveKeys([
        'id', 'sku', 'is_default', 'combination_label', 'attributes', 'media', 'price', 'availability',
    ]);
    expect($combination['price'])->toHaveKeys([
        'currency', 'base_amount_minor', 'final_amount_minor', 'discount_amount_minor',
        'on_sale', 'applied_promotions', 'calculated_at', 'signature',
    ]);
    expect($combination['price']['base_amount_minor'])->toBeInt();
});

it('returns a bounded facet payload', function (): void {
    PublicCatalogFixtures::publicProduct();
    $response = $this->getJson('/api/v1/catalog/products/facets')->assertOk();
    expect($response->json('data'))->toHaveKeys([
        'brands', 'categories', 'attributes', 'price_range', 'in_stock_count', 'on_sale_count',
    ]);
    expect($response->json('data.price_range.currency'))->toBe('GEL');
});

it('returns consistent error envelopes', function (): void {
    PublicCatalogFixtures::priceList();

    $this->getJson('/api/v1/catalog/products/missing-slug')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'CATALOG_PRODUCT_NOT_FOUND')
        ->assertJsonStructure(['error' => ['code', 'message'], 'meta' => ['request_id']]);

    $this->getJson('/api/v1/catalog/products?sort=nope')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CATALOG_FILTER_INVALID');
});

it('serves public catalog browse against the default GEL list even when empty', function (): void {
    $this->getJson('/api/v1/catalog/categories?locale=en')
        ->assertOk()
        ->assertJsonPath('meta.currency', 'GEL');

    $this->getJson('/api/v1/catalog/products?locale=en')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 0);

    $this->getJson('/api/v1/catalog/categories?currency=USD')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CATALOG_CURRENCY_UNSUPPORTED');
});
