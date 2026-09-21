<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domains\Catalog\PublicApi\Queries\GetPublicProductQuery;
use App\Domains\Catalog\PublicApi\Queries\GetPublicProductsQuery;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogContextFactory;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\PublicProductIndexRequest;
use App\Http\Resources\Api\V1\Catalog\PublicProductCardResource;
use App\Http\Resources\Api\V1\Catalog\PublicProductDetailResource;
use App\Http\Support\PublicCatalogResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicProductController extends Controller
{
    public function __construct(
        private readonly PublicCatalogContextFactory $contexts,
        private readonly GetPublicProductsQuery $list,
        private readonly GetPublicProductQuery $detail,
        private readonly PublicCatalogResponder $responder,
    ) {}

    public function index(PublicProductIndexRequest $request): JsonResponse
    {
        $context = $this->contexts->fromRequest($request);
        $filters = $request->filters();

        return $this->responder->cached($request, $context, 'products.index', $filters->toCacheArray(), function () use ($context, $filters, $request): array {
            $result = $this->list->paginate($context, $filters);
            $paginator = $result['paginator'];

            return [
                'data' => PublicProductCardResource::collection($result['cards'])->resolve(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                    'filters' => $filters->toCacheArray(),
                    'sort' => $filters->sort,
                    'locale' => $context->locale,
                    'currency' => $context->currency,
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                    'search_mode' => 'basic_mysql',
                ],
                'links' => [
                    'next' => $paginator->nextPageUrl(),
                    'prev' => $paginator->previousPageUrl(),
                ],
            ];
        });
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $context = $this->contexts->fromRequest($request);

        return $this->responder->cached($request, $context, 'products.show', ['slug' => $slug], function () use ($context, $slug, $request): array {
            $payload = $this->detail->find($context, $slug);

            return [
                'data' => (new PublicProductDetailResource($payload))->resolve(),
                'meta' => [
                    'locale' => $context->locale,
                    'currency' => $context->currency,
                    'used_fallback' => (bool) ($payload['used_fallback'] ?? false),
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ];
        });
    }
}
