<?php

declare(strict_types=1);

use App\Domains\Catalog\Search\Enums\SearchIndexType;
use App\Domains\Catalog\Search\Support\SearchIndexNamer;

it('names locale-specific versioned indexes and isolates the testing environment', function (): void {
    config(['search.index_prefix' => 'outdoor_local', 'app.env' => 'testing', 'search.schema_version' => 'v1']);

    $namer = new SearchIndexNamer;

    expect($namer->prefix())->toContain('test');
    expect($namer->uid(SearchIndexType::Variants, 'ka'))->toBe('outdoor_local_test_catalog_variants_ka_v1');
    expect($namer->uid(SearchIndexType::Brands, 'en'))->toBe('outdoor_local_test_brands_en_v1');
    expect($namer->rebuildUid(SearchIndexType::Categories, 'ka'))->toStartWith('outdoor_local_test_categories_ka_v1_rebuild_');
});

it('rejects unsupported locales', function (): void {
    $namer = new SearchIndexNamer;

    $namer->uid(SearchIndexType::Variants, 'fr');
})->throws(InvalidArgumentException::class);
