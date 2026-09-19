<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttributeValue
 */
final class AttributeValueListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AttributeValue $value */
        $value = $this->resource;
        $locale = CatalogLocales::isSupported((string) $request->query('locale', ''))
            ? (string) $request->query('locale')
            : CatalogLocales::default();

        return [
            'id' => $value->id,
            'attribute_id' => $value->attribute_id,
            'code' => $value->code,
            'name' => $value->localizedName($locale),
            'status' => $value->status->value,
            'sort_order' => $value->sort_order,
            'color_hex' => $value->color_hex,
            'created_at' => $value->created_at?->toIso8601String(),
            'updated_at' => $value->updated_at?->toIso8601String(),
            'deleted_at' => $value->deleted_at?->toIso8601String(),
        ];
    }
}
