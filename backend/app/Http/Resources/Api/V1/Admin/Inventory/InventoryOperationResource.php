<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Models\InventoryOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventoryOperation */
final class InventoryOperationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'reference' => [
                'type' => $this->reference_type,
                'id' => $this->reference_id,
            ],
            'reason_code' => $this->reason_code,
            'note' => $this->note,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
