<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Models\PriceList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PriceList */
final class PriceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'currency_code' => $this->currency_code,
            'status' => $this->status->value,
            'is_default' => $this->is_default,
            'priority' => $this->priority,
            'prices_include_tax' => $this->prices_include_tax,
            'priced_variant_count' => $this->when(isset($this->priced_variant_count), $this->priced_variant_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
