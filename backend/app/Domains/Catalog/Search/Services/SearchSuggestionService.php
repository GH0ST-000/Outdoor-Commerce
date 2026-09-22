<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\Search\Enums\SearchIndexType;
use App\Domains\Catalog\Search\Support\SearchTextNormalizer;
use Illuminate\Support\Facades\Cache;

final class SearchSuggestionService
{
    public function __construct(
        private readonly SearchIndexManager $indexes,
        private readonly SearchTextNormalizer $normalizer,
        private readonly SearchQueryService $queries,
    ) {}

    /**
     * @return array{query: string, products: list<array<string, mixed>>, categories: list<array<string, mixed>>, brands: list<array<string, mixed>>, fallback_used: bool, processing_time_ms: int}
     */
    public function suggest(PublicCatalogContextData $context, string $query, int $limit): array
    {
        $normalized = $this->normalizer->normalize($query, (int) config('search.max_query_length', 100));
        $min = (int) config('search.min_suggest_length', 2);
        $limit = max(1, min($limit, (int) config('search.suggest_limit', 8)));

        if (mb_strlen($normalized, 'UTF-8') < $min) {
            return [
                'query' => $normalized,
                'products' => [],
                'categories' => [],
                'brands' => [],
                'fallback_used' => false,
                'processing_time_ms' => 0,
            ];
        }

        $ttl = (int) config('search.autocomplete_ttl_seconds', 20);
        $version = $this->indexes->uid(SearchIndexType::Variants, $context->locale);
        $key = 'search:suggest:'.$context->locale.':'.$version.':'.hash('sha256', $normalized.'|'.$limit);

        /** @var array{query: string, products: list<array<string, mixed>>, categories: list<array<string, mixed>>, brands: list<array<string, mixed>>, fallback_used: bool, processing_time_ms: int} $payload */
        $payload = Cache::remember($key, $ttl, function () use ($context, $normalized, $limit): array {
            $grouped = $this->queries->grouped($context, $normalized, $limit);

            return [
                'query' => $grouped['query'],
                'products' => array_slice($grouped['products'], 0, $limit),
                'categories' => array_slice($grouped['categories'], 0, $limit),
                'brands' => array_slice($grouped['brands'], 0, $limit),
                'fallback_used' => $grouped['fallback_used'],
                'processing_time_ms' => $grouped['processing_time_ms'],
            ];
        });

        return $payload;
    }
}
