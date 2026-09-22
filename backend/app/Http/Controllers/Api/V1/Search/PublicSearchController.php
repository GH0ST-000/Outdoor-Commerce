<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Domains\Catalog\PublicApi\Services\PublicCatalogContextFactory;
use App\Domains\Catalog\Search\Data\SearchHit;
use App\Domains\Catalog\Search\Data\SearchPage;
use App\Domains\Catalog\Search\Services\SearchQueryService;
use App\Domains\Catalog\Search\Services\SearchResultHydrator;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Search\PublicSearchRequest;
use App\Http\Resources\Api\V1\Catalog\PublicProductCardResource;
use Illuminate\Http\JsonResponse;

final class PublicSearchController extends Controller
{
    public function __construct(
        private readonly PublicCatalogContextFactory $contexts,
        private readonly SearchQueryService $search,
        private readonly SearchResultHydrator $hydrator,
    ) {}

    public function __invoke(PublicSearchRequest $request): JsonResponse
    {
        $context = $this->contexts->fromRequest($request);
        $limit = $request->integer('limit') ?: (int) config('search.grouped_limit', 10);
        $grouped = $this->search->grouped($context, (string) $request->validated('q'), $limit);

        $productIds = [];
        foreach ($grouped['products'] as $hit) {
            if (isset($hit['product_id'])) {
                $id = (int) $hit['product_id'];
                if (! in_array($id, $productIds, true)) {
                    $productIds[] = $id;
                }
            }
        }

        $cards = $this->hydrator->hydrate($context, new SearchPage(
            hits: array_map(
                static fn (int $id): SearchHit => new SearchHit($id, 0, 0.0),
                $productIds,
            ),
            total: count($productIds),
            page: 1,
            perPage: $limit,
            mode: $grouped['mode'],
            fallbackUsed: $grouped['fallback_used'],
            processingTimeMs: $grouped['processing_time_ms'],
        ));

        return response()->json([
            'data' => [
                'query' => $grouped['query'],
                'products' => PublicProductCardResource::collection($cards)->resolve(),
                'categories' => array_map(static fn (array $hit): array => [
                    'id' => $hit['category_id'] ?? null,
                    'name' => $hit['name'] ?? null,
                    'slug' => $hit['slug'] ?? null,
                    'path' => $hit['path'] ?? null,
                    'product_count' => $hit['product_count'] ?? 0,
                ], $grouped['categories']),
                'brands' => array_map(static fn (array $hit): array => [
                    'id' => $hit['brand_id'] ?? null,
                    'name' => $hit['name'] ?? null,
                    'slug' => $hit['slug'] ?? null,
                    'path' => $hit['path'] ?? null,
                    'product_count' => $hit['product_count'] ?? 0,
                ], $grouped['brands']),
            ],
            'meta' => [
                'locale' => $context->locale,
                'fallback_used' => $grouped['fallback_used'],
                'processing_time_ms' => $grouped['processing_time_ms'],
                'search_mode' => $grouped['mode']->value,
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
