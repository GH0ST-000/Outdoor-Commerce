<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Data\PublicProductListFilterData;
use App\Domains\Catalog\Search\Contracts\SearchGateway;
use App\Domains\Catalog\Search\Data\SearchHit;
use App\Domains\Catalog\Search\Data\SearchPage;
use App\Domains\Catalog\Search\Enums\SearchIndexType;
use App\Domains\Catalog\Search\Enums\SearchMode;
use App\Domains\Catalog\Search\Exceptions\SearchQueryException;
use App\Domains\Catalog\Search\Exceptions\SearchUnavailableException;
use App\Domains\Catalog\Search\Support\SearchFilterBuilder;
use App\Domains\Catalog\Search\Support\SearchTextNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SearchQueryService
{
    public function __construct(
        private readonly SearchGateway $gateway,
        private readonly SearchIndexManager $indexes,
        private readonly SearchFilterBuilder $filters,
        private readonly SearchTextNormalizer $normalizer,
        private readonly SearchFallbackService $fallback,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('search.enabled', true);
    }

    public function searchProducts(PublicCatalogContextData $context, PublicProductListFilterData $filters): SearchPage
    {
        $started = microtime(true);
        $query = $this->normalizer->normalize($filters->q, (int) config('search.max_query_length', 100));
        if ($query === '' || $filters->q === null) {
            throw SearchQueryException::invalid('Search query must not be empty.');
        }
        if (mb_strlen($query, 'UTF-8') > (int) config('search.max_query_length', 100)) {
            throw SearchQueryException::invalid('Search query is too long.');
        }
        if ($filters->page > (int) config('search.max_page', 100)) {
            throw SearchQueryException::invalid('Requested page is too deep.');
        }

        if (! $this->enabled()) {
            return $this->fallback->products($context, $filters, SearchMode::Disabled, $started);
        }

        try {
            $params = [
                'filter' => $this->filters->variantFilters($context, $filters),
                'limit' => $filters->perPage,
                'offset' => ($filters->page - 1) * $filters->perPage,
                'attributesToRetrieve' => ['product_id', 'variant_id'],
                'facets' => ['brand_id', 'category_id', 'attribute_filter_keys', 'is_in_stock', 'is_on_sale', 'price_minor'],
                'showRankingScore' => true,
            ];
            $sort = $this->filters->sort($filters);
            if ($sort !== null) {
                $params['sort'] = $sort;
            }

            $raw = $this->gateway->search(
                $this->indexes->uid(SearchIndexType::Variants, $context->locale),
                $query,
                $params,
            );

            $hits = [];
            foreach (($raw['hits'] ?? []) as $hit) {
                if (! isset($hit['product_id'], $hit['variant_id'])) {
                    continue;
                }
                $hits[] = new SearchHit(
                    (int) $hit['product_id'],
                    (int) $hit['variant_id'],
                    isset($hit['_rankingScore']) ? (float) $hit['_rankingScore'] : 0.0,
                );
            }

            $total = (int) ($raw['estimatedTotalHits'] ?? $raw['totalHits'] ?? count($hits));
            $ms = (int) round((microtime(true) - $started) * 1000);
            $this->log('product_search', $context->locale, $ms, count($hits), false);

            return new SearchPage(
                hits: $hits,
                total: $total,
                page: $filters->page,
                perPage: $filters->perPage,
                mode: SearchMode::Meilisearch,
                fallbackUsed: false,
                processingTimeMs: $ms,
                facets: is_array($raw['facetDistribution'] ?? null) ? $raw['facetDistribution'] : [],
            );
        } catch (SearchUnavailableException $exception) {
            $this->logFailureOnce($exception);

            return $this->fallback->products($context, $filters, SearchMode::MysqlFallback, $started);
        } catch (Throwable $exception) {
            $this->logFailureOnce($exception);

            return $this->fallback->products($context, $filters, SearchMode::MysqlFallback, $started);
        }
    }

    /**
     * @return array{products: list<array<string, mixed>>, categories: list<array<string, mixed>>, brands: list<array<string, mixed>>, mode: SearchMode, fallback_used: bool, processing_time_ms: int, query: string}
     */
    public function grouped(PublicCatalogContextData $context, string $query, int $limit): array
    {
        $started = microtime(true);
        $normalized = $this->normalizer->normalize($query, (int) config('search.max_query_length', 100));
        $limit = max(1, min($limit, (int) config('search.grouped_limit', 10)));

        if (! $this->enabled()) {
            return $this->fallback->grouped($context, $normalized, $limit, $started);
        }

        try {
            $products = $this->gateway->search(
                $this->indexes->uid(SearchIndexType::Variants, $context->locale),
                $normalized,
                [
                    'limit' => $limit,
                    'attributesToRetrieve' => ['product_id', 'variant_id', 'name', 'slug', 'brand_name'],
                    'filter' => ['locale = "'.$context->locale.'"'],
                ],
            );
            $categories = $this->gateway->search(
                $this->indexes->uid(SearchIndexType::Categories, $context->locale),
                $normalized,
                ['limit' => $limit, 'filter' => ['locale = "'.$context->locale.'"']],
            );
            $brands = $this->gateway->search(
                $this->indexes->uid(SearchIndexType::Brands, $context->locale),
                $normalized,
                ['limit' => $limit, 'filter' => ['locale = "'.$context->locale.'"']],
            );

            $ms = (int) round((microtime(true) - $started) * 1000);
            $this->log('grouped_search', $context->locale, $ms, count($products['hits'] ?? []), false);

            return [
                'query' => $normalized,
                'products' => $products['hits'] ?? [],
                'categories' => $categories['hits'] ?? [],
                'brands' => $brands['hits'] ?? [],
                'mode' => SearchMode::Meilisearch,
                'fallback_used' => false,
                'processing_time_ms' => $ms,
            ];
        } catch (Throwable $exception) {
            $this->logFailureOnce($exception);

            return $this->fallback->grouped($context, $normalized, $limit, $started);
        }
    }

    /**
     * Unique product ids for the current search (bounded for facet counts).
     *
     * @return list<int>
     */
    public function matchingProductIds(PublicCatalogContextData $context, PublicProductListFilterData $filters): array
    {
        $wide = new PublicProductListFilterData(
            category: $filters->category,
            brands: $filters->brands,
            attributes: $filters->attributes,
            minPrice: $filters->minPrice,
            maxPrice: $filters->maxPrice,
            inStock: $filters->inStock,
            onSale: $filters->onSale,
            featured: $filters->featured,
            q: $filters->q,
            sort: $filters->sort,
            page: 1,
            perPage: 1000,
            includeDescendants: $filters->includeDescendants,
        );

        return $this->searchProducts($context, $wide)->productIds();
    }

    private function log(string $action, string $locale, int $ms, int $count, bool $fallback): void
    {
        Log::info('search.query', [
            'module' => 'search',
            'action' => $action,
            'locale' => $locale,
            'duration_ms' => $ms,
            'result_count' => $count,
            'fallback_used' => $fallback,
        ]);
    }

    private function logFailureOnce(Throwable $exception): void
    {
        $seconds = (int) config('search.failure_log_seconds', 30);
        if (! Cache::add('search:failure-log', 1, $seconds)) {
            return;
        }
        Log::warning('search.unavailable', [
            'module' => 'search',
            'action' => 'unavailable',
            'message' => $exception->getMessage(),
        ]);
    }
}
