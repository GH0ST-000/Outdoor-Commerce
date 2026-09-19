<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttributeValue
 */
final class AttributeValueDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AttributeValue $value */
        $value = $this->resource;
        $value->loadMissing(['translations', 'attribute']);

        return [
            'id' => $value->id,
            'attribute_id' => $value->attribute_id,
            'attribute' => $value->attribute !== null ? [
                'id' => $value->attribute->id,
                'code' => $value->attribute->code,
                'type' => $value->attribute->type->value,
                'status' => $value->attribute->status->value,
            ] : null,
            'code' => $value->code,
            'status' => $value->status->value,
            'sort_order' => $value->sort_order,
            'color_hex' => $value->color_hex,
            'metadata' => $value->metadata,
            'translations' => $value->translations->map(static fn ($translation): array => [
                'locale' => $translation->locale,
                'name' => $translation->name,
                'description' => $translation->description,
            ])->values()->all(),
            'created_by' => $value->created_by,
            'updated_by' => $value->updated_by,
            'created_at' => $value->created_at?->toIso8601String(),
            'updated_at' => $value->updated_at?->toIso8601String(),
            'deleted_at' => $value->deleted_at?->toIso8601String(),
        ];
    }
}
