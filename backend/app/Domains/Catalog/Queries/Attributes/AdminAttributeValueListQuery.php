<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Queries\Attributes;

use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminAttributeValueListQuery
{
    private const ALLOWED_SORTS = [
        'created_at',
        'updated_at',
        'sort_order',
        'code',
        'status',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AttributeValue>
     */
    public function paginate(Attribute $attribute, array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $locale = CatalogLocales::isSupported((string) ($filters['locale'] ?? ''))
            ? (string) $filters['locale']
            : CatalogLocales::default();
        $sort = in_array($filters['sort'] ?? 'sort_order', self::ALLOWED_SORTS, true)
            ? (string) ($filters['sort'] ?? 'sort_order')
            : 'sort_order';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = AttributeValue::query()
            ->where('attribute_id', $attribute->id)
            ->with([
                'translations' => fn ($q) => $q->select(['id', 'attribute_value_id', 'locale', 'name']),
            ]);

        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        if (! empty($filters['status'])) {
            $status = AttributeValueStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(function (Builder $builder) use ($like, $locale): void {
                $builder
                    ->where('code', 'like', $like)
                    ->orWhereHas('translations', function (Builder $translation) use ($like, $locale): void {
                        $translation->where('locale', $locale)->where('name', 'like', $like);
                    });
            });
        }

        return $query
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage);
    }
}
