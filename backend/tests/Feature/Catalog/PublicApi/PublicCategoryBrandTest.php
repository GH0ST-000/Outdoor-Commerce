<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\CatalogStatus;
use Tests\Support\PublicCatalogFixtures;

it('returns only active public categories and hides descendants of inactive ancestors', function (): void {
    PublicCatalogFixtures::priceList();
    $root = PublicCatalogFixtures::category([
        'ka_slug' => 'nadiroba',
        'ka_name' => 'ნადირობა',
        'en_slug' => 'hunting',
        'en_name' => 'Hunting',
    ]);
    PublicCatalogFixtures::category([
        'parent' => $root,
        'ka_slug' => 'optika',
        'ka_name' => 'ოპტიკა',
        'en_slug' => 'optics',
    ]);
    $inactive = PublicCatalogFixtures::category(['status' => CatalogStatus::Archived, 'ka_slug' => 'hidden-root']);
    PublicCatalogFixtures::category(['parent' => $inactive, 'ka_slug' => 'hidden-child']);

    $tree = $this->getJson('/api/v1/catalog/categories?locale=ka')->assertOk();
    $slugs = collect($tree->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('nadiroba')->not->toContain('hidden-root')->not->toContain('hidden-child');

    $detail = $this->getJson('/api/v1/catalog/categories/nadiroba?locale=ka')->assertOk();
    $detail->assertJsonPath('data.slug', 'nadiroba')
        ->assertJsonPath('data.name', 'ნადირობა')
        ->assertJsonPath('data.seo.canonical_path', '/catalog/nadiroba');
    expect(collect($detail->json('data.children'))->pluck('slug')->all())->toContain('optika');
    expect($detail->json('data'))->not->toHaveKey('products');

    $this->getJson('/api/v1/catalog/categories/hidden-root')->assertNotFound()
        ->assertJsonPath('error.code', 'CATALOG_CATEGORY_NOT_FOUND');
});

it('returns only active brands with default pagination of 10', function (): void {
    PublicCatalogFixtures::priceList();
    for ($i = 0; $i < 11; $i++) {
        PublicCatalogFixtures::brand(['ka_slug' => 'brand-'.$i, 'en_slug' => 'brand-'.$i, 'ka_name' => 'ბრენდი '.$i]);
    }
    PublicCatalogFixtures::brand(['status' => CatalogStatus::Archived, 'ka_slug' => 'archived-brand']);

    $list = $this->getJson('/api/v1/catalog/brands')->assertOk();
    expect($list->json('meta.pagination.per_page'))->toBe(10);
    expect($list->json('data'))->toHaveCount(10);
    expect(collect($list->json('data'))->pluck('slug')->all())->not->toContain('archived-brand');

    $this->getJson('/api/v1/catalog/brands/archived-brand')->assertNotFound()
        ->assertJsonPath('error.code', 'CATALOG_BRAND_NOT_FOUND');

    $detail = $this->getJson('/api/v1/catalog/brands/brand-0')->assertOk();
    expect($detail->json('data.slug'))->toBe('brand-0');
    expect($detail->json('data.seo.canonical_path'))->toBe('/brands/brand-0');
});
