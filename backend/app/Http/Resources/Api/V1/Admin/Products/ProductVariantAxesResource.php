<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Products;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductVariantAxesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $locale = CatalogLocales::isSupported((string) $request->query('locale', ''))
            ? (string) $request->query('locale')
            : CatalogLocales::default();
        $product->loadMissing('variantAttributes.translations');

        return [
            'product_id' => $product->id,
            'axes' => $product->variantAttributes->map(static function ($attribute) use ($locale): array {
                $pivot = $attribute->getRelation('pivot');

                return [
                    'attribute_id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->localizedName($locale),
                    'type' => $attribute->type->value,
                    'status' => $attribute->status->value,
                    'sort_order' => is_object($pivot) && isset($pivot->sort_order) ? (int) $pivot->sort_order : 0,
                ];
            })->values()->all(),
        ];
    }
}
