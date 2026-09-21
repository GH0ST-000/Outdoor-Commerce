<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventoryReservation */
final class InventoryReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reservation_key' => $this->reservation_key,
            'warehouse' => [
                'id' => $this->warehouse?->id,
                'code' => $this->warehouse?->code,
                'name' => $this->warehouse?->name,
            ],
            'variant' => [
                'id' => $this->variant?->id,
                'sku' => $this->variant?->sku,
                'barcode' => $this->variant?->barcode,
            ],
            'quantity' => $this->quantity,
            'status' => $this->status->value,
            'reference' => [
                'type' => $this->reference_type,
                'id' => $this->reference_id,
            ],
            'expires_at' => $this->expires_at?->toIso8601String(),
            'committed_at' => $this->committed_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'release_reason' => $this->release_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
