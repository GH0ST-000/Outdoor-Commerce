<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Products;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\Products\ProductReadinessService;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $locale = CatalogLocales::default();
        $translation = $product->translation($locale);
        $readiness = app(ProductReadinessService::class)->evaluate($product);

        return [
            'id' => $product->id,
            'name' => $translation?->name,
            'slug' => $translation?->slug,
            'status' => $product->status->value,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->localizedName($locale),
            ] : null,
            'primary_category' => $product->primaryCategory ? [
                'id' => $product->primaryCategory->id,
                'name' => $product->primaryCategory->localizedName($locale),
            ] : null,
            'category_count' => (int) ($product->categories_count ?? $product->categories->count()),
            'model_number' => $product->model_number,
            'is_featured' => $product->is_featured,
            'readiness' => [
                'ready' => $readiness['ready'],
                'issue_count' => $readiness['issue_count'],
            ],
            'published_at' => $product->published_at?->toIso8601String(),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
            'deleted_at' => $product->deleted_at?->toIso8601String(),
        ];
    }
}
