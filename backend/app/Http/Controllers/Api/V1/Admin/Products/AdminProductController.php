<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Domains\Catalog\Actions\Products\ArchiveProductAction;
use App\Domains\Catalog\Actions\Products\ChangeProductStatusAction;
use App\Domains\Catalog\Actions\Products\CreateProductAction;
use App\Domains\Catalog\Actions\Products\RestoreProductAction;
use App\Domains\Catalog\Actions\Products\UpdateProductAction;
use App\Domains\Catalog\DTOs\Products\ProductTranslationData;
use App\Domains\Catalog\DTOs\Products\ProductWriteData;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Queries\Products\AdminProductListQuery;
use App\Domains\Catalog\Services\Products\ProductReadinessService;
use App\Domains\Identity\Enums\Permission;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Products\AdminProductIndexRequest;
use App\Http\Requests\Api\V1\Admin\Products\ChangeProductStatusRequest;
use App\Http\Requests\Api\V1\Admin\Products\StoreProductRequest;
use App\Http\Requests\Api\V1\Admin\Products\UpdateProductRequest;
use App\Http\Resources\Api\V1\Admin\Products\ProductDetailResource;
use App\Http\Resources\Api\V1\Admin\Products\ProductListResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminProductController
{
    use AuthorizesRequests;

    public function index(
        AdminProductIndexRequest $request,
        AdminProductListQuery $query,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', Product::class);

        return ProductListResource::collection($query->paginate($request->validated()))
            ->additional([
                'meta' => [
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ]);
    }

    public function store(
        StoreProductRequest $request,
        CreateProductAction $action,
    ): JsonResponse {
        $this->authorize('create', Product::class);

        /** @var User $actor */
        $actor = $request->user();
        $data = $this->toWriteData($request->validated(), syncTranslations: true, defaultStatus: ProductStatus::Draft);

        if ($data->status === ProductStatus::Active) {
            abort_unless($actor->can(Permission::CatalogPublish->value), 403, 'Publishing permission is required.');
        }

        $product = $action->execute(
            $data,
            $actor,
            $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductDetailResource($product))
            ->additional([
                'meta' => [
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product): ProductDetailResource
    {
        $this->authorize('view', $product);
        $product->load([
            'translations',
            'categories.translations',
            'brand.translations',
            'primaryCategory.translations',
        ]);

        return (new ProductDetailResource($product))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        UpdateProductAction $action,
    ): ProductDetailResource {
        $this->authorize('update', $product);

        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $status = isset($validated['status'])
            ? ProductStatus::from((string) $validated['status'])
            : $product->status;

        if ($status === ProductStatus::Active && $product->status !== ProductStatus::Active) {
            $this->authorize('publish', $product);
        }

        $data = $this->toWriteData(
            array_merge([
                'brand_id' => $product->brand_id,
                'primary_category_id' => $product->primary_category_id,
                'status' => $product->status->value,
                'model_number' => $product->model_number,
                'manufacturer_part_number' => $product->manufacturer_part_number,
                'is_featured' => $product->is_featured,
                'sort_order' => $product->sort_order,
            ], $validated),
            syncTranslations: (bool) ($validated['sync_translations'] ?? isset($validated['translations'])),
            defaultStatus: $product->status,
            categoryIdsExplicit: array_key_exists('category_ids', $validated),
        );

        $updated = $action->execute(
            $product,
            $data,
            $actor,
            $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductDetailResource($updated))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function updateStatus(
        ChangeProductStatusRequest $request,
        Product $product,
        ChangeProductStatusAction $action,
    ): ProductDetailResource {
        $status = ProductStatus::from((string) $request->validated('status'));
        if ($status === ProductStatus::Active) {
            $this->authorize('publish', $product);
        } else {
            $this->authorize('update', $product);
        }

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $product,
            $status,
            $actor,
            $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductDetailResource($updated))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        Product $product,
        ArchiveProductAction $action,
    ): ProductDetailResource {
        $this->authorize('archive', $product);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $product,
            $actor,
            $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductDetailResource($updated))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function restore(
        Request $request,
        int $product,
        RestoreProductAction $action,
    ): ProductDetailResource {
        $model = Product::withTrashed()->findOrFail($product);
        $this->authorize('restore', $model);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $model,
            $actor,
            $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductDetailResource($updated))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function readiness(Request $request, Product $product, ProductReadinessService $service): JsonResponse
    {
        $this->authorize('view', $product);
        $product->load(['translations', 'categories', 'brand', 'primaryCategory']);

        return response()->json([
            'data' => $service->evaluate($product),
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function toWriteData(
        array $validated,
        bool $syncTranslations,
        ProductStatus $defaultStatus,
        bool $categoryIdsExplicit = true,
    ): ProductWriteData {
        $translations = [];
        foreach ($validated['translations'] ?? [] as $row) {
            $translations[] = new ProductTranslationData(
                locale: (string) $row['locale'],
                name: (string) $row['name'],
                slug: (string) $row['slug'],
                shortDescription: isset($row['short_description']) ? (string) $row['short_description'] : null,
                description: isset($row['description']) ? (string) $row['description'] : null,
                seoTitle: isset($row['seo_title']) ? (string) $row['seo_title'] : null,
                seoDescription: isset($row['seo_description']) ? (string) $row['seo_description'] : null,
            );
        }

        $status = isset($validated['status'])
            ? ProductStatus::from((string) $validated['status'])
            : $defaultStatus;

        /** @var list<int>|null $categoryIds */
        $categoryIds = $categoryIdsExplicit
            ? array_map('intval', $validated['category_ids'] ?? [])
            : null;

        return new ProductWriteData(
            brandId: array_key_exists('brand_id', $validated)
                ? ($validated['brand_id'] !== null ? (int) $validated['brand_id'] : null)
                : null,
            primaryCategoryId: array_key_exists('primary_category_id', $validated)
                ? ($validated['primary_category_id'] !== null ? (int) $validated['primary_category_id'] : null)
                : null,
            categoryIds: $categoryIds,
            status: $status,
            modelNumber: isset($validated['model_number']) ? (string) $validated['model_number'] : null,
            manufacturerPartNumber: isset($validated['manufacturer_part_number'])
                ? (string) $validated['manufacturer_part_number']
                : null,
            isFeatured: (bool) ($validated['is_featured'] ?? false),
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            translations: $translations,
            syncTranslations: $syncTranslations,
            removeEnglish: (bool) ($validated['remove_english'] ?? false),
        );
    }
}
