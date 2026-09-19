<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attribute
 */
final class AttributeDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Attribute $attribute */
        $attribute = $this->resource;
        $attribute->loadMissing('translations');

        return [
            'id' => $attribute->id,
            'code' => $attribute->code,
            'type' => $attribute->type->value,
            'status' => $attribute->status->value,
            'is_filterable' => $attribute->is_filterable,
            'sort_order' => $attribute->sort_order,
            'value_count' => (int) ($attribute->values_count ?? $attribute->values()->count()),
            'translations' => $attribute->translations->map(static fn ($translation): array => [
                'locale' => $translation->locale,
                'name' => $translation->name,
                'description' => $translation->description,
            ])->values()->all(),
            'created_by' => $attribute->created_by,
            'updated_by' => $attribute->updated_by,
            'created_at' => $attribute->created_at?->toIso8601String(),
            'updated_at' => $attribute->updated_at?->toIso8601String(),
            'deleted_at' => $attribute->deleted_at?->toIso8601String(),
        ];
    }
}
