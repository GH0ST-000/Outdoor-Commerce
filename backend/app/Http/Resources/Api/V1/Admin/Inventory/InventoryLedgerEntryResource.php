<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Models\InventoryLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventoryLedgerEntry */
final class InventoryLedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $operation = $this->operation;

        return [
            'id' => $this->id,
            'operation_id' => $this->inventory_operation_id,
            'operation_uuid' => $operation?->uuid,
            'operation_type' => $operation?->type?->value,
            'movement_type' => $this->movement_type->value,
            'quantity_delta' => $this->quantity_delta,
            'on_hand_after' => $this->on_hand_after,
            'reserved_after' => $this->reserved_after,
            'reason_code' => $operation?->reason_code,
            'note' => $operation?->note,
            'reference' => [
                'type' => $operation?->reference_type,
                'id' => $operation?->reference_id,
            ],
            'actor' => $operation?->performer ? [
                'id' => $operation->performer->id,
                'name' => $operation->performer->name,
            ] : null,
            'occurred_at' => $operation?->occurred_at?->toIso8601String(),
            'correlation_id' => $operation?->correlation_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
