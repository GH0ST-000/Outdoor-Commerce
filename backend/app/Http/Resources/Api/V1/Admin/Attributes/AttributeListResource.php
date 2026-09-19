<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attribute
 */
final class AttributeListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Attribute $attribute */
        $attribute = $this->resource;
        $locale = CatalogLocales::isSupported((string) $request->query('locale', ''))
            ? (string) $request->query('locale')
            : CatalogLocales::default();

        return [
            'id' => $attribute->id,
            'code' => $attribute->code,
            'name' => $attribute->localizedName($locale),
            'type' => $attribute->type->value,
            'status' => $attribute->status->value,
            'is_filterable' => $attribute->is_filterable,
            'sort_order' => $attribute->sort_order,
            'value_count' => (int) ($attribute->values_count ?? 0),
            'created_at' => $attribute->created_at?->toIso8601String(),
            'updated_at' => $attribute->updated_at?->toIso8601String(),
            'deleted_at' => $attribute->deleted_at?->toIso8601String(),
        ];
    }
}
