<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Domains\Catalog\Actions\Variants\SyncProductVariantAxesAction;
use App\Domains\Catalog\DTOs\Variants\VariantAxesData;
use App\Domains\Catalog\Models\Product;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Products\SyncProductVariantAxesRequest;
use App\Http\Resources\Api\V1\Admin\Products\ProductVariantAxesResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class AdminProductVariantAxesController
{
    use AuthorizesRequests;

    public function show(Request $request, Product $product): ProductVariantAxesResource
    {
        $this->authorize('view', $product);

        return (new ProductVariantAxesResource($product))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function sync(
        SyncProductVariantAxesRequest $request,
        Product $product,
        SyncProductVariantAxesAction $action,
    ): ProductVariantAxesResource {
        $this->authorize('update', $product);

        /** @var list<array{attribute_id: int, sort_order: int}> $axes */
        $axes = [];
        foreach ($request->validated('axes', []) as $index => $row) {
            $axes[] = [
                'attribute_id' => (int) $row['attribute_id'],
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : (int) $index,
            ];
        }

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $product,
            new VariantAxesData($axes),
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new ProductVariantAxesResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
