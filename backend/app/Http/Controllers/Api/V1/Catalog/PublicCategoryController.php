<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domains\Catalog\PublicApi\Queries\GetPublicCategoriesQuery;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogContextFactory;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Catalog\PublicCategoryResource;
use App\Http\Support\PublicCatalogResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicCategoryController extends Controller
{
    public function __construct(
        private readonly PublicCatalogContextFactory $contexts,
        private readonly GetPublicCategoriesQuery $query,
        private readonly PublicCatalogResponder $responder,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->contexts->fromRequest($request);

        return $this->responder->cached($request, $context, 'categories.tree', [], function () use ($context, $request): array {
            return [
                'data' => $this->query->tree($context),
                'meta' => [
                    'locale' => $context->locale,
                    'currency' => $context->currency,
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ];
        });
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $context = $this->contexts->fromRequest($request);

        return $this->responder->cached($request, $context, 'categories.show', ['slug' => $slug], function () use ($context, $slug, $request): array {
            return [
                'data' => (new PublicCategoryResource($this->query->detail($context, $slug)))->resolve(),
                'meta' => [
                    'locale' => $context->locale,
                    'currency' => $context->currency,
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ];
        });
    }
}
