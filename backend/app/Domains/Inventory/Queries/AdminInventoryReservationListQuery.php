<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Queries;

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminInventoryReservationListQuery
{
    private const ALLOWED_SORTS = ['created_at', 'expires_at', 'quantity', 'status'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, InventoryReservation>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $sort = in_array($filters['sort'] ?? 'created_at', self::ALLOWED_SORTS, true)
            ? (string) ($filters['sort'] ?? 'created_at')
            : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = InventoryReservation::query()
            ->with([
                'warehouse:id,code,name',
                'variant:id,product_id,sku,barcode',
            ]);

        if (! empty($filters['status'])) {
            $status = InventoryReservationStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['reference_type'])) {
            $query->where('reference_type', (string) $filters['reference_type']);
        }

        if (! empty($filters['reference_id'])) {
            $query->where('reference_id', (string) $filters['reference_id']);
        }

        if (! empty($filters['expires_before'])) {
            $query->where('expires_at', '<=', $filters['expires_before']);
        }

        if (! empty($filters['expires_after'])) {
            $query->where('expires_at', '>=', $filters['expires_after']);
        }

        return $query->orderBy($sort, $direction)->orderBy('id')->paginate($perPage);
    }
}
