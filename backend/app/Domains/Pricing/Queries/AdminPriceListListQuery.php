<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Queries;

use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Models\PriceList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminPriceListListQuery
{
    private const ALLOWED_SORTS = ['created_at', 'updated_at', 'code', 'name', 'status', 'priority', 'is_default', 'currency_code'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PriceList>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $sort = in_array($filters['sort'] ?? 'code', self::ALLOWED_SORTS, true)
            ? (string) ($filters['sort'] ?? 'code')
            : 'code';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = PriceList::query()
            ->withCount('variantPrices as priced_variant_count');

        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        if (! empty($filters['status'])) {
            $status = PriceListStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        $currency = $filters['currency_code'] ?? $filters['currency'] ?? null;
        if (! empty($currency)) {
            $query->where('currency_code', strtoupper((string) $currency));
        }

        if (array_key_exists('is_default', $filters) && $filters['is_default'] !== null && $filters['is_default'] !== '') {
            $query->where('is_default', filter_var($filters['is_default'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('code', 'like', $like)->orWhere('name', 'like', $like);
            });
        }

        return $query->orderBy($sort, $direction)->orderBy('id')->paginate($perPage);
    }
}
