<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Products;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\Variants\VariantReadinessService;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
final class ProductVariantDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->resource;
        $locale = CatalogLocales::isSupported((string) $request->query('locale', ''))
            ? (string) $request->query('locale')
            : CatalogLocales::default();
        $variant->loadMissing(['combinationRows.attribute.translations', 'combinationRows.attributeValue.translations']);

        return [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'status' => $variant->status->value,
            'is_default' => $variant->is_default,
            'sort_order' => $variant->sort_order,
            'combination_hash' => $variant->combination_hash,
            'combination_signature' => $variant->combination_signature,
            'attribute_values' => $variant->combinationRows->map(static fn ($row): array => [
                'attribute_id' => $row->attribute_id,
                'attribute_code' => $row->attribute?->code,
                'attribute_name' => $row->attribute?->localizedName($locale),
                'attribute_value_id' => $row->attribute_value_id,
                'attribute_value_code' => $row->attributeValue?->code,
                'attribute_value_name' => $row->attributeValue?->localizedName($locale),
                'color_hex' => $row->attributeValue?->color_hex,
            ])->values()->all(),
            'readiness' => app(VariantReadinessService::class)->evaluate($variant),
            'created_by' => $variant->created_by,
            'updated_by' => $variant->updated_by,
            'created_at' => $variant->created_at?->toIso8601String(),
            'updated_at' => $variant->updated_at?->toIso8601String(),
            'deleted_at' => $variant->deleted_at?->toIso8601String(),
        ];
    }
}
