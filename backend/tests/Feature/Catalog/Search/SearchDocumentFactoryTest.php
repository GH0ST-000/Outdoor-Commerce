<?php

declare(strict_types=1);

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Search\Services\SearchDocumentFactory;
use Tests\Support\PublicCatalogFixtures;

it('builds locale-specific variant documents without private fields', function (): void {
    $fixture = PublicCatalogFixtures::publicProduct([
        'ka_name' => 'ოპტიკური სამიზნე',
        'en_name' => 'Rifle Scope',
        'sku' => 'PRD-SCOPE-1',
        'price' => 12999,
        'model_number' => 'RS-390',
    ]);

    $documents = app(SearchDocumentFactory::class)->variantDocumentsForProduct($fixture['product']->id);
    $ka = collect($documents)->first(fn ($document) => $document->locale === 'ka');
    $en = collect($documents)->first(fn ($document) => $document->locale === 'en');

    expect($ka)->not->toBeNull()
        ->and($en)->not->toBeNull()
        ->and($ka->id)->toBe('var_'.$fixture['variant']->id.'_ka')
        ->and($en->id)->toBe('var_'.$fixture['variant']->id.'_en')
        ->and($ka->name)->toBe('ოპტიკური სამიზნე')
        ->and($en->name)->toBe('Rifle Scope')
        ->and($ka->priceMinor)->toBe(12999)
        ->and($ka->sku)->toBe('PRD-SCOPE-1')
        ->and($ka->searchAliases)->toContain('RS-390')
        ->and($ka->documentVersion)->not->toBe('')
        ->and($ka->primaryMediaId)->toBeInt();

    $payload = $ka->toArray();
    expect($payload)->not->toHaveKey('cost')
        ->and($payload)->not->toHaveKey('supplier')
        ->and($payload['price_minor'])->toBeInt()
        ->and($payload['category_ancestor_ids'])->toBeArray();
});

it('omits unpublished products from search documents', function (): void {
    $draft = PublicCatalogFixtures::publicProduct([
        'status' => ProductStatus::Draft,
        'published_at' => null,
        'ka_name' => 'დრაფტი',
    ]);

    expect(app(SearchDocumentFactory::class)->variantDocumentsForProduct($draft['product']->id))->toBe([]);
});

it('encodes same-variant attribute filter keys', function (): void {
    $axis = PublicCatalogFixtures::multiAxisProduct();
    $documents = app(SearchDocumentFactory::class)->variantDocumentsForProduct($axis['product']->id);
    $black = collect($documents)->first(fn ($document) => $document->variantId === $axis['black_xl']->id && $document->locale === 'ka');

    expect($black)->not->toBeNull()
        ->and($black->attributeFilterKeys)->toContain('attr_color_black')
        ->and($black->attributeFilterKeys)->toContain('attr_size_xl')
        ->and($black->attributeFilterKeys)->not->toContain('attr_size_l');
});
