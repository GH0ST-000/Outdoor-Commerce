<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Services\CatalogCache;
use Illuminate\Support\Facades\Cache;

final class CategoryDescendantResolver
{
    public function __construct(
        private readonly CatalogCache $catalogCache,
    ) {}

    /**
     * Active, non-deleted descendant IDs including `$categoryId`.
     *
     * @return list<int>
     */
    public function publicIdsIncludingSelf(int $categoryId): array
    {
        $version = $this->catalogCache->version();
        $key = "catalog:public:category-descendants:{$version}:{$categoryId}";

        /** @var list<int> $ids */
        $ids = Cache::remember($key, 300, function () use ($categoryId): array {
            return $this->walk($categoryId);
        });

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function walk(int $rootId): array
    {
        $max = (int) config('catalog.public.max_category_depth', 12);
        $collected = [];
        $frontier = [$rootId];
        $seen = [];

        for ($depth = 0; $depth <= $max && $frontier !== []; $depth++) {
            $rows = Category::query()
                ->whereIn('id', $frontier)
                ->where('status', CatalogStatus::Active->value)
                ->whereNull('deleted_at')
                ->get(['id', 'parent_id']);

            $next = [];
            foreach ($rows as $row) {
                if (isset($seen[$row->id])) {
                    continue;
                }
                $seen[$row->id] = true;
                $collected[] = (int) $row->id;
            }

            if ($rows->isEmpty()) {
                break;
            }

            $next = Category::query()
                ->whereIn('parent_id', $rows->pluck('id')->all())
                ->where('status', CatalogStatus::Active->value)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->reject(fn (int $id): bool => isset($seen[$id]))
                ->values()
                ->all();

            $frontier = $next;
        }

        return $collected;
    }
}
