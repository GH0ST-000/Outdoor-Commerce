<?php

declare(strict_types=1);

use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Services\InventoryCache;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Support\Facades\DB;
use Tests\Support\PublicCatalogFixtures;

it('caches equivalent public list requests regardless of parameter order', function (): void {
    PublicCatalogFixtures::publicProduct(['featured' => true]);

    $this->flushApplicationCacheSafely();
    $miss = $this->getJson('/api/v1/catalog/products?featured=1&sort=featured');
    $miss->assertOk();

    $hit = $this->getJson('/api/v1/catalog/products?sort=featured&featured=1');
    $hit->assertOk();
    expect($hit->json('data'))->toEqual($miss->json('data'));

    $ka = $this->getJson('/api/v1/catalog/products?locale=ka')->assertOk();
    $en = $this->getJson('/api/v1/catalog/products?locale=en')->assertOk();
    expect($ka->headers->get('ETag'))->not->toBe($en->headers->get('ETag'));

    $page2 = $this->getJson('/api/v1/catalog/products?page=2')->assertOk();
    expect($page2->json('meta.pagination.current_page'))->toBe(2);
});

it('invalidates public cache after catalog price inventory and media changes', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['ka_name' => 'კეში']);
    $first = $this->getJson('/api/v1/catalog/products/'.$fixture['ka_slug'])->assertOk();
    expect($first->json('data.name'))->toBe('კეში');

    $fixture['product']->translations()->where('locale', 'ka')->update(['name' => 'განახლებული']);
    $fixture['product']->touch();
    app(CatalogCache::class)->bump();
    PublicCatalogFixtures::rebuild($fixture['product']->id);

    $afterName = $this->getJson('/api/v1/catalog/products/'.$fixture['ka_slug'])->assertOk();
    expect($afterName->json('data.name'))->toBe('განახლებული');

    PricePeriod::query()->whereHas('variantPrice', fn ($q) => $q->where('product_variant_id', $fixture['variant']->id))
        ->update(['amount_minor' => 4242]);
    app(PricingCache::class)->bumpGlobal();
    PublicCatalogFixtures::rebuild($fixture['product']->id);
    expect($this->getJson('/api/v1/catalog/products/'.$fixture['ka_slug'])->json('data.variants.combinations.0.price.base_amount_minor'))
        ->toBe(4242);

    InventoryBalance::query()->where('product_variant_id', $fixture['variant']->id)->update(['on_hand' => 0]);
    app(InventoryCache::class)->bumpGlobal();
    PublicCatalogFixtures::rebuild($fixture['product']->id);
    expect($this->getJson('/api/v1/catalog/products/'.$fixture['ka_slug'])->json('data.availability.status'))
        ->toBe('out_of_stock');

    PublicCatalogFixtures::activePromotion(2500);
    app(PricingCache::class)->bumpGlobal();
    PublicCatalogFixtures::rebuild($fixture['product']->id);
    expect($this->getJson('/api/v1/catalog/products/'.$fixture['ka_slug'])->json('data.price.on_sale'))->toBeTrue();
});

it('does not invalidate cache when a failed transaction rolls back', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['ka_name' => 'ტრანზაქცია']);
    $this->getJson('/api/v1/catalog/products/'.$fixture['ka_slug'])->assertJsonPath('data.name', 'ტრანზაქცია');

    try {
        DB::transaction(function () use ($fixture): void {
            $fixture['product']->translations()->where('locale', 'ka')->update(['name' => 'არ უნდა გამოჩნდეს']);
            $fixture['product']->touch();
            throw new RuntimeException('nope');
        });
    } catch (RuntimeException) {
    }

    $this->getJson('/api/v1/catalog/products/'.$fixture['ka_slug'])
        ->assertJsonPath('data.name', 'ტრანზაქცია');
});

it('remains correct when the cache store is empty', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct();
    $this->flushApplicationCacheSafely();
    $this->getJson('/api/v1/catalog/products/'.$fixture['ka_slug'])->assertOk()
        ->assertJsonPath('data.id', $fixture['product']->id);
});
