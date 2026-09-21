<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use Tests\Support\PublicCatalogFixtures;

it('filters attributes with OR inside a group and AND across groups on the same variant', function (): void {
    $axis = PublicCatalogFixtures::multiAxisProduct();

    $black = $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => ['black']],
    ]))->assertOk();
    expect(collect($black->json('data'))->pluck('id')->all())->toContain($axis['product']->id);

    $eitherColor = $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => ['black', 'forest_green']],
    ]))->assertOk();
    expect(collect($eitherColor->json('data'))->pluck('id')->all())->toContain($axis['product']->id);

    $validCombo = $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => ['black'], 'size' => ['xl']],
    ]))->assertOk();
    expect(collect($validCombo->json('data'))->pluck('id')->all())->toContain($axis['product']->id);

    $splitAcrossVariants = $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => ['black'], 'size' => ['l']],
    ]))->assertOk();
    expect(collect($splitAcrossVariants->json('data'))->pluck('id')->all())->not->toContain($axis['product']->id);

    $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['not_a_real_attr' => ['black']],
    ]))->assertStatus(422)->assertJsonPath('error.code', 'CATALOG_FILTER_INVALID');

    $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => ['not_a_value']],
    ]))->assertStatus(422);

    $axis['color']->update(['status' => AttributeStatus::Archived]);
    $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => ['black']],
    ]))->assertStatus(422);

    $axis['color']->update(['status' => AttributeStatus::Active, 'is_filterable' => false]);
    $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => ['black']],
    ]))->assertStatus(422);
});

it('rejects archived values and bounds large filter arrays', function (): void {
    $axis = PublicCatalogFixtures::multiAxisProduct();
    $axis['black']->update(['status' => AttributeValueStatus::Archived]);

    $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => ['black']],
    ]))->assertStatus(422);

    $tooMany = [];
    for ($i = 0; $i < 30; $i++) {
        $tooMany[] = 'v'.$i;
    }
    $this->getJson('/api/v1/catalog/products?'.http_build_query([
        'attribute' => ['color' => $tooMany],
    ]))->assertStatus(422);
});
