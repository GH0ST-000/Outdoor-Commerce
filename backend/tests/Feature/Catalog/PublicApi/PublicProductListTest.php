<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\PublicCatalogFixtures;

it('paginates with a default of 10 and enforces the maximum page size', function (): void {
    for ($i = 0; $i < 12; $i++) {
        PublicCatalogFixtures::publicProduct(['sort_order' => $i, 'ka_name' => 'პროდუქტი '.$i]);
    }

    $first = $this->getJson('/api/v1/catalog/products')->assertOk();
    expect($first->json('meta.pagination.per_page'))->toBe(10);
    expect($first->json('meta.pagination.total'))->toBe(12);
    expect($first->json('data'))->toHaveCount(10);

    $second = $this->getJson('/api/v1/catalog/products?page=2')->assertOk();
    $firstIds = collect($first->json('data'))->pluck('id')->all();
    $secondIds = collect($second->json('data'))->pluck('id')->all();
    expect(array_intersect($firstIds, $secondIds))->toBe([]);

    $this->getJson('/api/v1/catalog/products?per_page=0')->assertStatus(422);
    $this->getJson('/api/v1/catalog/products?per_page=200')->assertStatus(422)
        ->assertJsonPath('error.code', 'CATALOG_FILTER_INVALID');
});

it('filters by category descendants brands featured stock sale and price', function (): void {
    $parent = PublicCatalogFixtures::category(['ka_slug' => 'hunting', 'en_slug' => 'hunting']);
    $child = PublicCatalogFixtures::category(['parent' => $parent, 'ka_slug' => 'optics', 'en_slug' => 'optics']);
    $other = PublicCatalogFixtures::category(['ka_slug' => 'camping', 'en_slug' => 'camping']);
    $brandA = PublicCatalogFixtures::brand(['ka_slug' => 'alpha', 'en_slug' => 'alpha']);
    $brandB = PublicCatalogFixtures::brand(['ka_slug' => 'beta', 'en_slug' => 'beta']);

    $inParent = PublicCatalogFixtures::publicProduct([
        'category' => $parent,
        'brand' => $brandA,
        'price' => 20000,
        'stock' => 4,
        'featured' => true,
        'ka_name' => 'მშობელი',
    ]);
    $inChild = PublicCatalogFixtures::publicProduct([
        'category' => $child,
        'brand' => $brandB,
        'price' => 8000,
        'stock' => 0,
        'ka_name' => 'შვილი',
    ]);
    PublicCatalogFixtures::publicProduct([
        'category' => $other,
        'brand' => $brandA,
        'price' => 50000,
        'ka_name' => 'სხვა',
    ]);

    $promo = PublicCatalogFixtures::activePromotion(2000);
    PublicCatalogFixtures::rebuild($inParent['product']->id);

    $byCategory = $this->getJson('/api/v1/catalog/products?category=hunting')->assertOk();
    $names = collect($byCategory->json('data'))->pluck('name')->all();
    expect($names)->toContain('მშობელი')->toContain('შვილი')->not->toContain('სხვა');

    $directOnly = $this->getJson('/api/v1/catalog/products?category=hunting&include_descendants=0')->assertOk();
    expect(collect($directOnly->json('data'))->pluck('name')->all())->toContain('მშობელი')->not->toContain('შვილი');

    $brands = $this->getJson('/api/v1/catalog/products?brand[]=alpha&brand[]=beta')->assertOk();
    expect($brands->json('meta.pagination.total'))->toBeGreaterThanOrEqual(2);

    $featured = $this->getJson('/api/v1/catalog/products?featured=1')->assertOk();
    expect(collect($featured->json('data'))->pluck('id')->all())->toContain($inParent['product']->id);

    $inStock = $this->getJson('/api/v1/catalog/products?in_stock=1')->assertOk();
    expect(collect($inStock->json('data'))->pluck('id')->all())
        ->toContain($inParent['product']->id)
        ->not->toContain($inChild['product']->id);

    $onSale = $this->getJson('/api/v1/catalog/products?on_sale=1')->assertOk();
    expect(collect($onSale->json('data'))->pluck('id')->all())->toContain($inParent['product']->id);

    $price = $this->getJson('/api/v1/catalog/products?min_price=7000&max_price=9000')->assertOk();
    expect(collect($price->json('data'))->pluck('id')->all())->toContain($inChild['product']->id);

    $this->getJson('/api/v1/catalog/products?min_price=9000&max_price=1000')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CATALOG_FILTER_INVALID');

    $this->getJson('/api/v1/catalog/products?sort=popularity')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CATALOG_FILTER_INVALID');

    $this->getJson('/api/v1/catalog/products?unknown=1')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CATALOG_FILTER_INVALID');

    unset($promo);
});

it('supports basic mysql search and rejects empty or oversized queries', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct([
        'ka_name' => 'ბინოკლი კონდორი',
        'ka_slug' => 'binokli-kondori',
        'sku' => 'PRD-BINO-1',
    ]);

    $this->getJson('/api/v1/catalog/products?'.http_build_query(['q' => 'ბინოკლი', 'locale' => 'ka']))->assertOk()
        ->assertJsonPath('data.0.id', $fixture['product']->id)
        ->assertJsonPath('meta.search_mode', 'basic_mysql');

    $this->getJson('/api/v1/catalog/products?q=PRD-BINO-1')->assertOk()
        ->assertJsonPath('data.0.id', $fixture['product']->id);

    $this->getJson('/api/v1/catalog/products?'.http_build_query(['q' => '   ']))->assertStatus(422);
    $this->getJson('/api/v1/catalog/products?'.http_build_query(['q' => str_repeat('a', 200)]))->assertStatus(422);
});

it('keeps list payloads compact and query counts bounded', function (): void {
    for ($i = 0; $i < 8; $i++) {
        PublicCatalogFixtures::publicProduct(['ka_name' => 'ბარათი '.$i]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/catalog/products')->assertOk();
    $queryCount = count(DB::getQueryLog());

    expect($queryCount)->toBeLessThan(40);
    expect($response->json('data.0'))->not->toHaveKey('description');
    expect($response->json('data.0'))->not->toHaveKey('gallery');
    expect($response->json('data.0'))->not->toHaveKey('variants');
    expect($response->json('data.0.price'))->toHaveKeys(['currency', 'min_final_amount_minor', 'on_sale']);
});

it('sorts by final price deterministically', function (): void {
    $cheap = PublicCatalogFixtures::publicProduct(['price' => 3000, 'ka_name' => 'იაფი']);
    $mid = PublicCatalogFixtures::publicProduct(['price' => 9000, 'ka_name' => 'საშუალო']);
    $high = PublicCatalogFixtures::publicProduct(['price' => 40000, 'ka_name' => 'ძვირი']);

    $asc = collect($this->getJson('/api/v1/catalog/products?sort=price_asc')->json('data'))->pluck('id')->all();
    expect($asc[0])->toBe($cheap['product']->id);
    expect($asc[1])->toBe($mid['product']->id);
    expect($asc[2])->toBe($high['product']->id);

    $desc = collect($this->getJson('/api/v1/catalog/products?sort=price_desc')->json('data'))->pluck('id')->all();
    expect($desc[0])->toBe($high['product']->id);
});
