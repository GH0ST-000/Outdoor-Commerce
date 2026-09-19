<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Queries\Products;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminProductListQuery
{
    private const ALLOWED_SORTS = [
        'created_at',
        'updated_at',
        'published_at',
        'sort_order',
        'status',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $locale = CatalogLocales::isSupported((string) ($filters['locale'] ?? ''))
            ? (string) $filters['locale']
            : CatalogLocales::default();
        $sort = in_array($filters['sort'] ?? 'created_at', self::ALLOWED_SORTS, true)
            ? (string) ($filters['sort'] ?? 'created_at')
            : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Product::query()
            ->with([
                'translations' => fn ($q) => $q->select([
                    'id', 'product_id', 'locale', 'name', 'slug',
                ]),
                'brand.translations' => fn ($q) => $q->select(['id', 'brand_id', 'locale', 'name', 'slug']),
                'primaryCategory.translations' => fn ($q) => $q->select(['id', 'category_id', 'locale', 'name', 'slug']),
                // Readiness inspects variant status/default flags for every row.
                'variants' => fn ($q) => $q->select(['id', 'product_id', 'status', 'is_default']),
            ])
            ->withCount('categories');

        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        if (! empty($filters['status'])) {
            $status = ProductStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if (array_key_exists('brand_id', $filters) && $filters['brand_id'] !== null && $filters['brand_id'] !== '') {
            $query->where('brand_id', (int) $filters['brand_id']);
        }

        if (! empty($filters['primary_category_id'])) {
            $query->where('primary_category_id', (int) $filters['primary_category_id']);
        }

        if (! empty($filters['category_id'])) {
            $categoryId = (int) $filters['category_id'];
            $query->whereExists(function ($sub) use ($categoryId): void {
                $sub->selectRaw('1')
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'products.id')
                    ->where('category_product.category_id', $categoryId);
            });
        }

        if (array_key_exists('is_featured', $filters) && $filters['is_featured'] !== null && $filters['is_featured'] !== '') {
            $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (! empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $builder) use ($like, $locale): void {
                $builder
                    ->where('model_number', 'like', $like)
                    ->orWhere('manufacturer_part_number', 'like', $like)
                    ->orWhereHas('translations', function (Builder $translation) use ($like, $locale): void {
                        $translation
                            ->where('locale', $locale)
                            ->where(function (Builder $inner) use ($like): void {
                                $inner->where('name', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
            });
        }

        return $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
