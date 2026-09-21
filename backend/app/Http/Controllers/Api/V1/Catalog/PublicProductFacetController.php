<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domains\Catalog\PublicApi\Queries\GetPublicProductFacetsQuery;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogContextFactory;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\PublicProductIndexRequest;
use App\Http\Resources\Api\V1\Catalog\PublicProductFacetResource;
use App\Http\Support\PublicCatalogResponder;
use Illuminate\Http\JsonResponse;

final class PublicProductFacetController extends Controller
{
    public function __construct(
        private readonly PublicCatalogContextFactory $contexts,
        private readonly GetPublicProductFacetsQuery $query,
        private readonly PublicCatalogResponder $responder,
    ) {}

    public function show(PublicProductIndexRequest $request): JsonResponse
    {
        $context = $this->contexts->fromRequest($request);
        $filters = $request->filters();

        return $this->responder->cached($request, $context, 'products.facets', $filters->toCacheArray(), function () use ($context, $filters, $request): array {
            return [
                'data' => (new PublicProductFacetResource($this->query->execute($context, $filters)))->resolve(),
                'meta' => [
                    'filters' => $filters->toCacheArray(),
                    'locale' => $context->locale,
                    'currency' => $context->currency,
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                    'search_mode' => 'basic_mysql',
                ],
            ];
        });
    }
}
