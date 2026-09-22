<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Domains\Catalog\PublicApi\Services\PublicCatalogContextFactory;
use App\Domains\Catalog\Search\Data\SearchHit;
use App\Domains\Catalog\Search\Data\SearchPage;
use App\Domains\Catalog\Search\Enums\SearchMode;
use App\Domains\Catalog\Search\Services\SearchResultHydrator;
use App\Domains\Catalog\Search\Services\SearchSuggestionService;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Search\PublicSearchSuggestionRequest;
use App\Http\Resources\Api\V1\Catalog\PublicProductCardResource;
use Illuminate\Http\JsonResponse;

final class PublicSearchSuggestionController extends Controller
{
    public function __construct(
        private readonly PublicCatalogContextFactory $contexts,
        private readonly SearchSuggestionService $suggestions,
        private readonly SearchResultHydrator $hydrator,
    ) {}

    public function __invoke(PublicSearchSuggestionRequest $request): JsonResponse
    {
        $context = $this->contexts->fromRequest($request);
        $limit = $request->integer('limit') ?: (int) config('search.suggest_limit', 8);
        $payload = $this->suggestions->suggest($context, (string) $request->input('q', ''), $limit);

        $productIds = [];
        foreach ($payload['products'] as $hit) {
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
            mode: $payload['fallback_used']
                ? SearchMode::MysqlFallback
                : SearchMode::Meilisearch,
            fallbackUsed: $payload['fallback_used'],
            processingTimeMs: $payload['processing_time_ms'],
        ));

        return response()->json([
            'data' => [
                'query' => $payload['query'],
                'products' => PublicProductCardResource::collection($cards)->resolve(),
                'categories' => array_map(static fn (array $hit): array => [
                    'id' => $hit['category_id'] ?? null,
                    'name' => $hit['name'] ?? null,
                    'slug' => $hit['slug'] ?? null,
                    'path' => $hit['path'] ?? null,
                ], $payload['categories']),
                'brands' => array_map(static fn (array $hit): array => [
                    'id' => $hit['brand_id'] ?? null,
                    'name' => $hit['name'] ?? null,
                    'slug' => $hit['slug'] ?? null,
                    'path' => $hit['path'] ?? null,
                ], $payload['brands']),
            ],
            'meta' => [
                'locale' => $context->locale,
                'fallback_used' => $payload['fallback_used'],
                'processing_time_ms' => $payload['processing_time_ms'],
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
