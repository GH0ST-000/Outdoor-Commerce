<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Queries;

use App\Domains\Catalog\Contracts\CatalogLocales;
use App\Domains\Inventory\Models\InventoryBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminInventoryListQuery
{
    private const ALLOWED_SORTS = ['on_hand', 'reserved', 'updated_at', 'last_movement_at', 'warehouse_id', 'product_variant_id'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, InventoryBalance>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $locale = CatalogLocales::isSupported((string) ($filters['locale'] ?? ''))
            ? (string) $filters['locale']
            : CatalogLocales::default();
        $sort = in_array($filters['sort'] ?? 'updated_at', self::ALLOWED_SORTS, true)
            ? (string) ($filters['sort'] ?? 'updated_at')
            : 'updated_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = InventoryBalance::query()
            ->select('inventory_balances.*')
            ->with([
                'warehouse:id,code,name,status',
                'variant:id,product_id,sku,barcode,combination_signature',
                'variant.product:id',
                'variant.product.translations' => fn ($q) => $q->select(['id', 'product_id', 'locale', 'name']),
            ]);

        if (! empty($filters['warehouse_id'])) {
            $query->where('inventory_balances.warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('inventory_balances.product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->whereHas('variant', fn (Builder $v) => $v->where('product_id', (int) $filters['product_id']));
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->whereHas('variant', function (Builder $variant) use ($like, $locale): void {
                $variant
                    ->where('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhereHas('product.translations', fn (Builder $t) => $t->where('locale', $locale)->where('name', 'like', $like));
            });
        }

        if (! empty($filters['low_stock'])) {
            $query->whereRaw('GREATEST(0, on_hand - reserved - safety_stock) <= reorder_point');
        }

        if (! empty($filters['out_of_stock'])) {
            $query->whereRaw('GREATEST(0, on_hand - reserved - safety_stock) = 0');
        }

        if (! empty($filters['has_reservations'])) {
            $query->where('reserved', '>', 0);
        }

        return $query->orderBy($sort, $direction)->orderBy('inventory_balances.id')->paginate($perPage);
    }
}
