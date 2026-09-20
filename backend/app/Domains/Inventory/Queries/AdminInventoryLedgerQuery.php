<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Queries;

use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminInventoryLedgerQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, InventoryLedgerEntry>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);

        $query = InventoryLedgerEntry::query()
            ->with(['operation:id,uuid,type,reason_code,note,reference_type,reference_id,performed_by,correlation_id,occurred_at', 'operation.performer:id,name,email']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['movement_type'])) {
            $movement = InventoryMovementType::tryFrom((string) $filters['movement_type']);
            if ($movement !== null) {
                $query->where('movement_type', $movement->value);
            }
        }

        if (! empty($filters['operation_type'])) {
            $operation = InventoryOperationType::tryFrom((string) $filters['operation_type']);
            if ($operation !== null) {
                $query->whereHas('operation', fn (Builder $op) => $op->where('type', $operation->value));
            }
        }

        if (! empty($filters['reference_type'])) {
            $query->whereHas('operation', fn (Builder $op) => $op->where('reference_type', (string) $filters['reference_type']));
        }

        if (! empty($filters['reference_id'])) {
            $query->whereHas('operation', fn (Builder $op) => $op->where('reference_id', (string) $filters['reference_id']));
        }

        if (! empty($filters['performed_by'])) {
            $query->whereHas('operation', fn (Builder $op) => $op->where('performed_by', (int) $filters['performed_by']));
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }
}
