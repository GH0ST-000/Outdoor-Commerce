<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use Tests\Support\PublicCatalogFixtures;

it('lists an active complete product and hides draft archived and deleted ones', function (): void {
    $public = PublicCatalogFixtures::publicProduct(['ka_name' => 'საჯარო პროდუქტი', 'en_name' => 'Public product']);
    $draft = PublicCatalogFixtures::publicProduct(['status' => ProductStatus::Draft, 'ka_name' => 'დრაფტი']);
    $archived = PublicCatalogFixtures::publicProduct(['status' => ProductStatus::Archived, 'ka_name' => 'არქივი']);
    $deleted = PublicCatalogFixtures::publicProduct(['ka_name' => 'წაშლილი']);
    $deleted['product']->delete();
    PublicCatalogFixtures::rebuild($deleted['product']->id);

    $response = $this->getJson('/api/v1/catalog/products?locale=ka');

    $response->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 10)
        ->assertJsonPath('meta.currency', 'GEL')
        ->assertJsonPath('meta.locale', 'ka');

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($public['product']->id)
        ->not->toContain($draft['product']->id)
        ->not->toContain($archived['product']->id)
        ->not->toContain($deleted['product']->id);

    $this->getJson('/api/v1/catalog/products/'.$draft['ka_slug'])->assertNotFound()
        ->assertJsonPath('error.code', 'CATALOG_PRODUCT_NOT_FOUND');
    $this->getJson('/api/v1/catalog/products/'.$archived['ka_slug'])->assertNotFound();
    $this->getJson('/api/v1/catalog/products/'.$deleted['ka_slug'])->assertNotFound();
});

it('hides products under inactive categories brands or missing media and prices', function (): void {
    $inactiveCategory = PublicCatalogFixtures::category(['status' => CatalogStatus::Archived]);
    PublicCatalogFixtures::publicProduct([
        'category' => $inactiveCategory,
        'ka_name' => 'არააქტიური კატეგორია',
    ]);

    $parent = PublicCatalogFixtures::category(['status' => CatalogStatus::Archived, 'ka_slug' => 'parent-archived']);
    $child = PublicCatalogFixtures::category(['parent' => $parent, 'ka_slug' => 'child-active']);
    PublicCatalogFixtures::publicProduct(['category' => $child, 'ka_name' => 'შვილი']);

    $inactiveBrand = PublicCatalogFixtures::brand(['status' => CatalogStatus::Archived]);
    PublicCatalogFixtures::publicProduct(['brand' => $inactiveBrand, 'ka_name' => 'არააქტიური ბრენდი']);

    $unbranded = PublicCatalogFixtures::publicProduct(['brand' => null, 'ka_name' => 'უბრენდო']);
    $noVariantPrice = PublicCatalogFixtures::publicProduct(['priced' => false, 'ka_name' => 'უფასო']);
    $noMedia = PublicCatalogFixtures::publicProduct(['media' => false, 'ka_name' => 'უსურათო']);
    $outOfStock = PublicCatalogFixtures::publicProduct(['stock' => 0, 'ka_name' => 'არ არის მარაგში']);

    $list = $this->getJson('/api/v1/catalog/products?per_page=48')->assertOk();
    $names = collect($list->json('data'))->pluck('name')->all();

    expect($names)->toContain('უბრენდო')
        ->toContain('არ არის მარაგში')
        ->not->toContain('არააქტიური კატეგორია')
        ->not->toContain('შვილი')
        ->not->toContain('არააქტიური ბრენდი')
        ->not->toContain('უფასო')
        ->not->toContain('უსურათო');

    $detail = $this->getJson('/api/v1/catalog/products/'.$outOfStock['ka_slug'])->assertOk();
    expect($detail->json('data.availability.status'))->toBe('out_of_stock');
    expect($detail->json('data.availability.purchasable'))->toBeFalse();
    expect($detail->json())->not->toHaveKey('data.created_by');
    expect(json_encode($detail->json()))->not->toContain('password')
        ->not->toContain('warehouse')
        ->not->toContain('original_path');
});

it('keeps out-of-stock products public while marking them not purchasable', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['stock' => 0]);

    $this->getJson('/api/v1/catalog/products/'.$fixture['ka_slug'])
        ->assertOk()
        ->assertJsonPath('data.availability.status', 'out_of_stock')
        ->assertJsonPath('data.availability.purchasable', false);

    $projection = PublicCatalogProductProjection::query()
        ->where('product_id', $fixture['product']->id)
        ->first();
    expect($projection?->is_public)->toBeTrue();
});
