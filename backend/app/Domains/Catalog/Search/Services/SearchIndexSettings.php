<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\Search\Enums\SearchIndexType;

final class SearchIndexSettings
{
    /**
     * @return array<string, mixed>
     */
    public function for(SearchIndexType $type, string $locale): array
    {
        /** @var array<string, list<list<string>>> $synonymsConfig */
        $synonymsConfig = config('search.synonyms', []);
        $pairs = $synonymsConfig[$locale] ?? [];
        $synonyms = [];
        foreach ($pairs as $group) {
            foreach ($group as $word) {
                $synonyms[$word] = array_values(array_filter($group, fn (string $item): bool => $item !== $word));
            }
        }

        /** @var array<string, list<string>> $stopConfig */
        $stopConfig = config('search.stop_words', []);
        $stopWords = $stopConfig[$locale] ?? [];

        return match ($type) {
            SearchIndexType::Variants => [
                'searchableAttributes' => [
                    'sku',
                    'name',
                    'search_aliases',
                    'brand_name',
                    'category_name',
                    'attribute_value_codes',
                    'short_description',
                    'searchable_name',
                ],
                'filterableAttributes' => [
                    'product_id',
                    'variant_id',
                    'brand_id',
                    'category_id',
                    'category_ids',
                    'category_ancestor_ids',
                    'attribute_filter_keys',
                    'price_minor',
                    'is_in_stock',
                    'is_on_sale',
                    'is_featured',
                    'locale',
                ],
                'sortableAttributes' => [
                    'price_minor',
                    'publication_timestamp',
                    'merchandising_priority',
                    'popularity_score',
                    'name_sort',
                    'is_featured',
                    'is_in_stock',
                ],
                'displayedAttributes' => [
                    'id',
                    'product_id',
                    'variant_id',
                    'locale',
                    'name',
                    'slug',
                    'brand_name',
                    'sku',
                    'document_version',
                ],
                'rankingRules' => [
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'sort',
                    'exactness',
                    'merchandising_priority:desc',
                    'is_in_stock:desc',
                    'popularity_score:desc',
                ],
                'distinctAttribute' => 'product_id',
                'typoTolerance' => [
                    'enabled' => true,
                    'minWordSizeForTypos' => [
                        'oneTypo' => 5,
                        'twoTypos' => 9,
                    ],
                    'disableOnAttributes' => ['sku'],
                ],
                'pagination' => ['maxTotalHits' => 10000],
                'faceting' => ['maxValuesPerFacet' => 100],
                'synonyms' => $synonyms,
                'stopWords' => $stopWords,
            ],
            SearchIndexType::Categories, SearchIndexType::Brands => [
                'searchableAttributes' => ['name', 'slug'],
                'filterableAttributes' => ['locale'],
                'sortableAttributes' => ['product_count'],
                'displayedAttributes' => ['id', 'name', 'slug', 'path', 'product_count', 'locale'],
                'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'exactness'],
                'typoTolerance' => [
                    'enabled' => true,
                    'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8],
                ],
                'synonyms' => $synonyms,
                'stopWords' => $stopWords,
            ],
        };
    }
}
