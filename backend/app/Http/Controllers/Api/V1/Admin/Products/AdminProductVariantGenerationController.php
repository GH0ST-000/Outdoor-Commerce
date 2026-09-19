<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Domains\Catalog\Actions\Variants\GenerateProductVariantsAction;
use App\Domains\Catalog\Actions\Variants\PreviewVariantGenerationAction;
use App\Domains\Catalog\DTOs\Variants\VariantGenerationData;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Products\GenerateProductVariantsRequest;
use App\Http\Resources\Api\V1\Admin\Products\ProductVariantListResource;
use App\Http\Resources\Api\V1\Admin\Products\VariantGenerationPreviewResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminProductVariantGenerationController
{
    use AuthorizesRequests;

    public function preview(
        GenerateProductVariantsRequest $request,
        Product $product,
        PreviewVariantGenerationAction $action,
    ): VariantGenerationPreviewResource {
        $this->authorize('viewAny', ProductVariant::class);

        $preview = $action->execute($product, $this->toData($request));

        return (new VariantGenerationPreviewResource($preview))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function generate(
        GenerateProductVariantsRequest $request,
        Product $product,
        GenerateProductVariantsAction $action,
    ): JsonResponse {
        $this->authorize('create', ProductVariant::class);

        $data = $this->toData($request);

        if ($data->status === ProductVariantStatus::Active) {
            $this->authorize('publish', new ProductVariant);
        }

        /** @var User $actor */
        $actor = $request->user();
        $result = $action->execute(
            $product,
            $data,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return ProductVariantListResource::collection($result['created'])
            ->additional([
                'meta' => [
                    'request_id' => $this->requestId($request),
                    'summary' => $result['summary'],
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    private function toData(GenerateProductVariantsRequest $request): VariantGenerationData
    {
        $status = $request->validated('status');

        return new VariantGenerationData(
            selection: $request->selection(),
            status: $status !== null
                ? ProductVariantStatus::from((string) $status)
                : ProductVariantStatus::Draft,
        );
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
