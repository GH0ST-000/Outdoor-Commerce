<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Support\CatalogLocales;

final class PublicBreadcrumbBuilder
{
    public function __construct(
        private readonly PublicCatalogUrlGenerator $urls,
    ) {}

    /**
     * @return list<array{name: string, slug: string, path: string}>
     */
    public function forCategory(Category $category, string $locale): array
    {
        $trail = [];
        $current = $category;
        $seen = [];
        $max = (int) config('catalog.public.max_category_depth', 12);

        for ($i = 0; $i < $max && $current !== null; $i++) {
            if (isset($seen[$current->id])) {
                break;
            }
            $seen[$current->id] = true;
            $current->loadMissing(['translations', 'parent']);

            if (! $current->trashed() && $current->status === CatalogStatus::Active) {
                $translation = $current->translation($locale) ?? $current->translation(CatalogLocales::fallback());
                if ($translation !== null && $translation->slug !== '') {
                    array_unshift($trail, [
                        'name' => $translation->name,
                        'slug' => $translation->slug,
                        'path' => $this->urls->categoryPath($translation->slug),
                    ]);
                }
            }

            $current = $current->parent;
        }

        return $trail;
    }
}
