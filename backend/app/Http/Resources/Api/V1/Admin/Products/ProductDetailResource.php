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
final class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $locale = CatalogLocales::default();
        $readiness = app(ProductReadinessService::class)->evaluate($product);

        return [
            'id' => $product->id,
            'status' => $product->status->value,
            'brand_id' => $product->brand_id,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->localizedName($locale),
                'status' => $product->brand->status->value,
            ] : null,
            'primary_category_id' => $product->primary_category_id,
            'primary_category' => $product->primaryCategory ? [
                'id' => $product->primaryCategory->id,
                'name' => $product->primaryCategory->localizedName($locale),
                'status' => $product->primaryCategory->status->value,
            ] : null,
            'categories' => $product->categories->map(static function ($category): array {
                $pivot = $category->getRelation('pivot');

                return [
                    'id' => $category->id,
                    'name' => $category->localizedName(CatalogLocales::default()),
                    'status' => $category->status->value,
                    'sort_order' => is_object($pivot) && isset($pivot->sort_order)
                        ? (int) $pivot->sort_order
                        : 0,
                ];
            })->values()->all(),
            'model_number' => $product->model_number,
            'manufacturer_part_number' => $product->manufacturer_part_number,
            'is_featured' => $product->is_featured,
            'sort_order' => $product->sort_order,
            'published_at' => $product->published_at?->toIso8601String(),
            'translations' => $product->translations->map(fn ($t) => [
                'locale' => $t->locale,
                'name' => $t->name,
                'slug' => $t->slug,
                'short_description' => $t->short_description,
                'description' => $t->description,
                'seo_title' => $t->seo_title,
                'seo_description' => $t->seo_description,
            ])->values()->all(),
            'readiness' => $readiness,
            'created_by' => $product->created_by,
            'updated_by' => $product->updated_by,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
            'deleted_at' => $product->deleted_at?->toIso8601String(),
        ];
    }
}
