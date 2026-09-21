<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domains\Catalog\PublicApi\Queries\GetPublicBrandsQuery;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogContextFactory;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\PublicBrandIndexRequest;
use App\Http\Resources\Api\V1\Catalog\PublicBrandResource;
use App\Http\Support\PublicCatalogResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicBrandController extends Controller
{
    public function __construct(
        private readonly PublicCatalogContextFactory $contexts,
        private readonly GetPublicBrandsQuery $query,
        private readonly PublicCatalogResponder $responder,
    ) {}

    public function index(PublicBrandIndexRequest $request): JsonResponse
    {
        $context = $this->contexts->fromRequest($request);
        $page = max(1, $request->integer('page') ?: 1);
        $perPage = $request->integer('per_page') ?: (int) config('catalog.public.pagination.default_per_page', 10);

        return $this->responder->cached($request, $context, 'brands.index', [
            'page' => $page,
            'per_page' => $perPage,
        ], function () use ($context, $page, $perPage, $request): array {
            $paginator = $this->query->paginate($context, $page, $perPage);
            $data = collect($paginator->items())
                ->map(fn ($brand): array => $this->query->serialize($brand, $context))
                ->values()
                ->all();

            return [
                'data' => $data,
                'meta' => [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                    'locale' => $context->locale,
                    'currency' => $context->currency,
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
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

        return $this->responder->cached($request, $context, 'brands.show', ['slug' => $slug], function () use ($context, $slug, $request): array {
            $brand = $this->query->resolve($context, $slug);

            return [
                'data' => (new PublicBrandResource($this->query->serialize($brand, $context, true)))->resolve(),
                'meta' => [
                    'locale' => $context->locale,
                    'currency' => $context->currency,
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ];
        });
    }
}
