<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Queries;

use App\Domains\Inventory\Enums\WarehouseStatus;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminWarehouseListQuery
{
    private const ALLOWED_SORTS = ['created_at', 'updated_at', 'code', 'name', 'status', 'is_default'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Warehouse>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $sort = in_array($filters['sort'] ?? 'code', self::ALLOWED_SORTS, true) ? (string) ($filters['sort'] ?? 'code') : 'code';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = Warehouse::query();

        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        if (! empty($filters['status'])) {
            $status = WarehouseStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if (array_key_exists('is_default', $filters) && $filters['is_default'] !== null && $filters['is_default'] !== '') {
            $query->where('is_default', filter_var($filters['is_default'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('address_line_1', 'like', $like);
            });
        }

        return $query->orderBy($sort, $direction)->orderBy('id')->paginate($perPage);
    }
}
