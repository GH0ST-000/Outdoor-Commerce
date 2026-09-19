<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Domains\Catalog\Actions\Variants\ArchiveProductVariantAction;
use App\Domains\Catalog\Actions\Variants\ChangeProductVariantStatusAction;
use App\Domains\Catalog\Actions\Variants\CreateProductVariantAction;
use App\Domains\Catalog\Actions\Variants\RestoreProductVariantAction;
use App\Domains\Catalog\Actions\Variants\SetDefaultProductVariantAction;
use App\Domains\Catalog\Actions\Variants\UpdateProductVariantAction;
use App\Domains\Catalog\DTOs\Variants\ProductVariantWriteData;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Queries\Variants\AdminProductVariantListQuery;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Products\AdminProductVariantIndexRequest;
use App\Http\Requests\Api\V1\Admin\Products\ArchiveProductVariantRequest;
use App\Http\Requests\Api\V1\Admin\Products\ChangeProductVariantStatusRequest;
use App\Http\Requests\Api\V1\Admin\Products\StoreProductVariantRequest;
use App\Http\Requests\Api\V1\Admin\Products\UpdateProductVariantRequest;
use App\Http\Resources\Api\V1\Admin\Products\ProductVariantDetailResource;
use App\Http\Resources\Api\V1\Admin\Products\ProductVariantListResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminProductVariantController
{
    use AuthorizesRequests;

    public function index(
        AdminProductVariantIndexRequest $request,
        Product $product,
        AdminProductVariantListQuery $query,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', ProductVariant::class);

        return ProductVariantListResource::collection($query->paginate($product, $request->validated()))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function store(
        StoreProductVariantRequest $request,
        Product $product,
        CreateProductVariantAction $action,
    ): JsonResponse {
        $this->authorize('create', ProductVariant::class);

        /** @var User $actor */
        $actor = $request->user();
        $data = $this->toWriteData($request->validated(), ProductVariantStatus::Draft);

        if ($data->status === ProductVariantStatus::Active) {
            $this->authorize('publish', new ProductVariant);
        }

        $variant = $action->execute(
            $product,
            $data,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductVariantDetailResource($variant))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product, ProductVariant $variant): ProductVariantDetailResource
    {
        $this->authorize('view', $variant);
        $this->assertOwnership($product, $variant);

        return (new ProductVariantDetailResource($variant))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function update(
        UpdateProductVariantRequest $request,
        Product $product,
        ProductVariant $variant,
        UpdateProductVariantAction $action,
    ): ProductVariantDetailResource {
        $this->authorize('update', $variant);
        $this->assertOwnership($product, $variant);

        /** @var User $actor */
        $actor = $request->user();
        $data = $this->toWriteData($request->validated(), $variant->status, $variant->sort_order);

        if ($data->status === ProductVariantStatus::Active && $variant->status !== ProductVariantStatus::Active) {
            $this->authorize('publish', $variant);
        }

        $updated = $action->execute(
            $variant,
            $data,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductVariantDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function updateStatus(
        ChangeProductVariantStatusRequest $request,
        Product $product,
        ProductVariant $variant,
        ChangeProductVariantStatusAction $action,
    ): ProductVariantDetailResource {
        $status = ProductVariantStatus::from((string) $request->validated('status'));

        if ($status === ProductVariantStatus::Active) {
            $this->authorize('publish', $variant);
        } else {
            $this->authorize('update', $variant);
        }

        $this->assertOwnership($product, $variant);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $variant,
            $status,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductVariantDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function setDefault(
        Request $request,
        Product $product,
        ProductVariant $variant,
        SetDefaultProductVariantAction $action,
    ): ProductVariantDetailResource {
        $this->authorize('update', $variant);
        $this->assertOwnership($product, $variant);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $variant,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductVariantDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function destroy(
        ArchiveProductVariantRequest $request,
        Product $product,
        ProductVariant $variant,
        ArchiveProductVariantAction $action,
    ): ProductVariantDetailResource {
        $this->authorize('archive', $variant);
        $this->assertOwnership($product, $variant);

        $replacement = $request->validated('replacement_variant_id');

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $variant,
            $actor,
            $replacement !== null ? (int) $replacement : null,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductVariantDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function restore(
        Request $request,
        Product $product,
        int $variant,
        RestoreProductVariantAction $action,
    ): ProductVariantDetailResource {
        /** @var ProductVariant $model */
        $model = ProductVariant::withTrashed()->findOrFail($variant);
        $this->authorize('restore', $model);
        $this->assertOwnership($product, $model);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $model,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductVariantDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    private function assertOwnership(Product $product, ProductVariant $variant): void
    {
        abort_unless((int) $variant->product_id === (int) $product->id, 404);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function toWriteData(
        array $validated,
        ProductVariantStatus $defaultStatus,
        int $defaultSortOrder = 0,
    ): ProductVariantWriteData {
        /** @var list<array{attribute_id: int, attribute_value_id: int}>|null $pairs */
        $pairs = null;
        if (array_key_exists('attribute_values', $validated)) {
            $pairs = [];
            foreach ($validated['attribute_values'] as $row) {
                $pairs[] = [
                    'attribute_id' => (int) $row['attribute_id'],
                    'attribute_value_id' => (int) $row['attribute_value_id'],
                ];
            }
        }

        return new ProductVariantWriteData(
            sku: isset($validated['sku']) ? (string) $validated['sku'] : null,
            barcode: isset($validated['barcode']) ? (string) $validated['barcode'] : null,
            status: isset($validated['status'])
                ? ProductVariantStatus::from((string) $validated['status'])
                : $defaultStatus,
            sortOrder: array_key_exists('sort_order', $validated) ? (int) $validated['sort_order'] : $defaultSortOrder,
            attributeValues: $pairs,
            barcodeProvided: array_key_exists('barcode', $validated),
            isDefault: array_key_exists('is_default', $validated) ? (bool) $validated['is_default'] : null,
        );
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
