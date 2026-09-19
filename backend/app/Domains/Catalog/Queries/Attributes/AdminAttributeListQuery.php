<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Queries\Attributes;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminAttributeListQuery
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
     * @return LengthAwarePaginator<int, Attribute>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 50);
        $locale = CatalogLocales::isSupported((string) ($filters['locale'] ?? ''))
            ? (string) $filters['locale']
            : CatalogLocales::default();
        $sort = in_array($filters['sort'] ?? 'sort_order', self::ALLOWED_SORTS, true)
            ? (string) ($filters['sort'] ?? 'sort_order')
            : 'sort_order';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query = Attribute::query()
            ->with([
                'translations' => fn ($q) => $q->select(['id', 'attribute_id', 'locale', 'name']),
            ])
            ->withCount('values');

        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        if (! empty($filters['status'])) {
            $status = AttributeStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if (! empty($filters['type'])) {
            $type = AttributeType::tryFrom((string) $filters['type']);
            if ($type !== null) {
                $query->where('type', $type->value);
            }
        }

        if (array_key_exists('is_filterable', $filters) && $filters['is_filterable'] !== null && $filters['is_filterable'] !== '') {
            $query->where('is_filterable', filter_var($filters['is_filterable'], FILTER_VALIDATE_BOOLEAN));
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
