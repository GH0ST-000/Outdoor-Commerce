<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Support\Variants\VariantCombination;
use Illuminate\Support\Facades\DB;
use Tests\Support\PublicCatalogFixtures;

it('returns public product detail with seo variants and no private internals', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct([
        'ka_slug' => 'samizne-detali',
        'en_slug' => 'scope-detail',
        'price' => 12999,
    ]);
    $promo = PublicCatalogFixtures::activePromotion(1500);
    PublicCatalogFixtures::rebuild($fixture['product']->id);

    $finish = Attribute::factory()->active()->filterable()->color()->code('finish')->create();
    $matte = AttributeValue::factory()->forAttribute($finish)->active()->code('matte')->create();
    $gloss = AttributeValue::factory()->forAttribute($finish)->active()->code('gloss')->create();
    $fixture['product']->variantAttributes()->sync([$finish->id => ['sort_order' => 0]]);
    $pricedCombination = VariantCombination::fromPairs([[
        'attribute_id' => (int) $finish->id,
        'attribute_value_id' => (int) $matte->id,
    ]]);
    $fixture['variant']->combinationRows()->create([
        'attribute_id' => (int) $finish->id,
        'attribute_value_id' => (int) $matte->id,
    ]);
    $fixture['variant']->update([
        'combination_hash' => $pricedCombination->hash,
        'combination_signature' => $pricedCombination->signature,
    ]);

    $unpriced = ProductVariant::factory()
        ->forProduct($fixture['product'])
        ->active()
        ->sku('PRD-UNPRICED-1')
        ->withValues([$gloss])
        ->create();
    PublicCatalogFixtures::rebuild($fixture['product']->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/catalog/products/samizne-detali?locale=ka')->assertOk();
    $queryCount = count(DB::getQueryLog());

    $response->assertJsonPath('data.slug', 'samizne-detali')
        ->assertJsonPath('data.seo.canonical_path', '/products/samizne-detali')
        ->assertJsonPath('data.price.currency', 'GEL')
        ->assertJsonPath('data.price.on_sale', true);

    $variantIds = collect($response->json('data.variants.combinations'))->pluck('id')->all();
    expect($variantIds)->toContain($fixture['variant']->id)->not->toContain($unpriced->id);

    $price = $response->json('data.variants.combinations.0.price');
    expect($price['final_amount_minor'])->toBeLessThan($price['base_amount_minor']);
    expect($price['applied_promotions'][0]['code'])->toBe($promo->code);
    expect($response->json('data.default_variant_id'))->toBe($fixture['variant']->id);
    expect(json_encode($response->json()))
        ->not->toContain('originals/')
        ->not->toContain('media_private')
        ->not->toContain('warehouse_id')
        ->not->toContain('safety_stock')
        ->not->toContain('reserved');
    expect($queryCount)->toBeLessThan(100);

    $unpriced->update(['status' => ProductVariantStatus::Archived]);
});

it('builds a variant matrix for one-axis multi-axis empty and out-of-stock combinations', function (): void {
    $single = PublicCatalogFixtures::publicProduct();
    $oneAxis = $this->getJson('/api/v1/catalog/products/'.$single['ka_slug'])->assertOk();
    expect($oneAxis->json('data.variants.axes'))->toBe([]);
    expect($oneAxis->json('data.variants.combinations'))->toHaveCount(1);

    $multi = PublicCatalogFixtures::multiAxisProduct();
    $detail = $this->getJson('/api/v1/catalog/products/'.$multi['ka_slug'])->assertOk();
    $axes = collect($detail->json('data.variants.axes'))->pluck('code')->all();
    expect($axes)->toContain('color')->toContain('size');
    expect($detail->json('data.variants.combinations'))->toHaveCount(2);

    $oos = collect($detail->json('data.variants.combinations'))
        ->firstWhere('id', $multi['green_l']->id);
    expect($oos['availability']['status'])->toBe('out_of_stock');
    expect($oos['availability']['purchasable'])->toBeFalse();

    $withMedia = collect($detail->json('data.variants.combinations'))
        ->firstWhere('id', $multi['black_xl']->id);
    expect($withMedia['media'])->not->toBeEmpty();
});

it('does not apply expired promotions', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct(['price' => 10000, 'ka_slug' => 'promo-expired']);
    PublicCatalogFixtures::activePromotion(5000, now()->subDay()->toImmutable());
    PublicCatalogFixtures::rebuild($fixture['product']->id);

    $response = $this->getJson('/api/v1/catalog/products/promo-expired')->assertOk();
    expect($response->json('data.price.on_sale'))->toBeFalse();
    expect($response->json('data.variants.combinations.0.price.applied_promotions'))->toBe([]);
});

it('falls back to product media when a variant has none', function (): void {
    $multi = PublicCatalogFixtures::multiAxisProduct();
    $detail = $this->getJson('/api/v1/catalog/products/'.$multi['ka_slug'])->assertOk();
    $green = collect($detail->json('data.variants.combinations'))->firstWhere('id', $multi['green_l']->id);
    expect($green['media'])->not->toBeEmpty();
});
