<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Data\PublicProductListFilterData;
use App\Domains\Catalog\PublicApi\Queries\GetPublicProductsQuery;
use App\Domains\Catalog\Search\Data\SearchHit;
use App\Domains\Catalog\Search\Data\SearchPage;
use App\Domains\Catalog\Search\Enums\SearchMode;
use App\Domains\Catalog\Search\Exceptions\SearchUnavailableException;

final class SearchFallbackService
{
    public function __construct(
        private readonly GetPublicProductsQuery $products,
    ) {}

    public function products(
        PublicCatalogContextData $context,
        PublicProductListFilterData $filters,
        SearchMode $mode,
        float $started,
    ): SearchPage {
        if (! (bool) config('search.fallback_enabled', true) && $mode !== SearchMode::Disabled) {
            throw SearchUnavailableException::infrastructure();
        }

        $ids = $this->products->matchingIds($context, $filters);
        $offset = ($filters->page - 1) * $filters->perPage;
        $slice = array_slice($ids, $offset, $filters->perPage);
        $hits = array_map(
            static fn (int $id): SearchHit => new SearchHit($id, 0, 0.0),
            $slice,
        );

        return new SearchPage(
            hits: $hits,
            total: count($ids),
            page: $filters->page,
            perPage: $filters->perPage,
            mode: $mode === SearchMode::Disabled ? SearchMode::BasicMysql : SearchMode::MysqlFallback,
            fallbackUsed: $mode !== SearchMode::Disabled,
            processingTimeMs: (int) round((microtime(true) - $started) * 1000),
        );
    }

    /**
     * @return array{products: list<array<string, mixed>>, categories: list<array<string, mixed>>, brands: list<array<string, mixed>>, mode: SearchMode, fallback_used: bool, processing_time_ms: int, query: string}
     */
    public function grouped(PublicCatalogContextData $context, string $query, int $limit, float $started): array
    {
        if (! (bool) config('search.fallback_enabled', true)) {
            throw SearchUnavailableException::infrastructure();
        }

        $filters = new PublicProductListFilterData(
            q: $query,
            page: 1,
            perPage: $limit,
        );
        $ids = array_slice($this->products->matchingIds($context, $filters), 0, $limit);
        $productHits = array_map(static fn (int $id): array => ['product_id' => $id], $ids);

        return [
            'query' => $query,
            'products' => $productHits,
            'categories' => [],
            'brands' => [],
            'mode' => SearchMode::MysqlFallback,
            'fallback_used' => true,
            'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }
}
