<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Queries\Variants;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminProductVariantListQuery
{
    private const ALLOWED_SORTS = [
        'created_at',
        'updated_at',
        'sort_order',
        'sku',
        'status',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ProductVariant>
     */
    public function paginate(Product $product, array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $sort = in_array($filters['sort'] ?? 'sort_order', self::ALLOWED_SORTS, true)
            ? (string) ($filters['sort'] ?? 'sort_order')
            : 'sort_order';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = ProductVariant::query()
            ->where('product_id', $product->id)
            ->with([
                'combinationRows.attribute.translations',
                'combinationRows.attributeValue.translations',
            ]);

        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        if (! empty($filters['status'])) {
            $status = ProductVariantStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if (array_key_exists('is_default', $filters) && $filters['is_default'] !== null && $filters['is_default'] !== '') {
            $query->where('is_default', filter_var($filters['is_default'], FILTER_VALIDATE_BOOLEAN));
        }

        /** @var list<int> $attributeIds */
        $attributeIds = array_values(array_filter(array_map('intval', (array) ($filters['attribute_id'] ?? []))));
        foreach ($attributeIds as $attributeId) {
            $query->whereHas('combinationRows', fn (Builder $rows) => $rows->where('attribute_id', $attributeId));
        }

        /** @var list<int> $valueIds */
        $valueIds = array_values(array_filter(array_map('intval', (array) ($filters['attribute_value_id'] ?? []))));
        foreach ($valueIds as $valueId) {
            $query->whereHas('combinationRows', fn (Builder $rows) => $rows->where('attribute_value_id', $valueId));
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('sku', 'like', $like)->orWhere('barcode', 'like', $like);
            });
        }

        return $query
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage);
    }
}
