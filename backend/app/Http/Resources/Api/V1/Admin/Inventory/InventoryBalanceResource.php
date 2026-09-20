<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Inventory;

use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Inventory\Models\InventoryBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventoryBalance */
final class InventoryBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = CatalogLocales::isSupported((string) $request->query('locale', ''))
            ? (string) $request->query('locale')
            : CatalogLocales::default();
        $quantities = $this->quantities();
        $productName = $this->variant?->product?->translations
            ->firstWhere('locale', $locale)?->name
            ?? $this->variant?->product?->translations->first()?->name;

        return [
            'warehouse' => [
                'id' => $this->warehouse?->id,
                'code' => $this->warehouse?->code,
                'name' => $this->warehouse?->name,
            ],
            'product' => [
                'id' => $this->variant?->product_id,
                'name' => $productName,
            ],
            'variant' => [
                'id' => $this->variant?->id,
                'sku' => $this->variant?->sku,
                'barcode' => $this->variant?->barcode,
                'combination_label' => $this->variant?->combination_signature,
            ],
            'quantities' => $quantities->toArray(),
            'status' => [
                'low_stock' => $quantities->isLowStock(),
                'out_of_stock' => $quantities->isOutOfStock(),
            ],
            'version' => $this->version,
            'last_movement_at' => $this->last_movement_at?->toIso8601String(),
        ];
    }
}
