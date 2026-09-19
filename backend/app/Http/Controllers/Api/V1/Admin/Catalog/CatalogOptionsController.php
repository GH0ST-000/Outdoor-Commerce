<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Identity\Enums\Permission;
use App\Domains\Shared\Support\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight selectors for product forms (Day 6 minimum surface).
 */
final class CatalogOptionsController
{
    public function categories(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::CatalogView->value), 403);

        $locale = CatalogLocales::default();
        $items = Category::query()
            ->with('translations')
            ->whereIn('status', [CatalogStatus::Draft->value, CatalogStatus::Active->value])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->localizedName($locale),
                'status' => $category->status->value,
                'parent_id' => $category->parent_id,
            ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function brands(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::CatalogView->value), 403);

        $locale = CatalogLocales::default();
        $items = Brand::query()
            ->with('translations')
            ->whereIn('status', [CatalogStatus::Draft->value, CatalogStatus::Active->value])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->localizedName($locale),
                'status' => $brand->status->value,
            ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }
}
