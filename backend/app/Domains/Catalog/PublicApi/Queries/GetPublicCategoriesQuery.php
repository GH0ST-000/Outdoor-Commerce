<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Queries;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Exceptions\PublicCatalogNotFoundException;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use App\Domains\Catalog\PublicApi\Services\PublicBreadcrumbBuilder;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogUrlGenerator;
use App\Domains\Catalog\PublicApi\Services\PublicProductEligibility;
use App\Domains\Catalog\PublicApi\Services\PublicSeoComposer;
use App\Domains\Catalog\Support\CatalogLocales;

final class GetPublicCategoriesQuery
{
    public function __construct(
        private readonly PublicCatalogUrlGenerator $urls,
        private readonly PublicProductEligibility $eligibility,
        private readonly PublicBreadcrumbBuilder $breadcrumbs,
        private readonly PublicSeoComposer $seo,
    ) {}

    /**
     * Active public category tree. Descendants of inactive ancestors are omitted.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(PublicCatalogContextData $context): array
    {
        $categories = Category::query()
            ->where('status', CatalogStatus::Active->value)
            ->whereNull('deleted_at')
            ->with(['translations'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $grouped = [];
        foreach ($categories as $category) {
            $parentId = (int) ($category->parent_id ?? 0);
            $grouped[$parentId][] = $category;
        }

        return $this->buildNodes($grouped, 0, $context, 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(PublicCatalogContextData $context, string $slug): array
    {
        $category = $this->resolve($context, $slug);
        if ($category === null || ! $this->eligibility->categoryAncestryIsPublic($category)) {
            throw PublicCatalogNotFoundException::category();
        }

        $category->loadMissing(['translations', 'parent.translations', 'children.translations']);
        $translation = $category->translation($context->locale);
        $usedFallback = $translation !== null && $translation->locale !== $context->locale;
        $slugValue = (string) $translation?->slug;
        $children = $category->children
            ->filter(fn (Category $child): bool => $this->eligibility->categoryAncestryIsPublic($child))
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->map(fn (Category $child): array => $this->summary($child, $context))
            ->all();

        $parent = $category->parent;
        $counts = $this->productCounts([$category->id]);

        $localeSlugs = [];
        foreach (CatalogLocales::all() as $locale) {
            $localeSlugs[$locale] = $category->translations->firstWhere('locale', $locale)?->slug;
        }

        return [
            'id' => $category->id,
            'name' => $translation?->name,
            'slug' => $slugValue,
            'description' => null,
            'seo' => $this->seo->compose(
                title: $translation !== null ? (string) $translation->name : '',
                description: null,
                canonicalPath: $this->urls->categoryPath($slugValue),
                alternatePaths: $this->urls->alternateLocalePaths($localeSlugs, 'category'),
            ),
            'breadcrumbs' => $this->breadcrumbs->forCategory($category, $context->locale),
            'parent' => $parent !== null && $this->eligibility->categoryAncestryIsPublic($parent)
                ? $this->summary($parent, $context)
                : null,
            'children' => $children,
            'product_count' => $counts[$category->id] ?? 0,
            'canonical_path' => $this->urls->categoryPath($slugValue),
            'alternate_locale_paths' => $this->urls->alternateLocalePaths($localeSlugs, 'category'),
            'used_fallback' => $usedFallback,
        ];
    }

    public function resolve(PublicCatalogContextData $context, string $slug): ?Category
    {
        $match = Category::query()
            ->where('status', CatalogStatus::Active->value)
            ->whereHas('translations', function ($query) use ($context, $slug): void {
                $query->where('slug', $slug)->whereIn('locale', CatalogLocales::slugLookupLocales($context->locale));
            })
            ->with('translations')
            ->first();

        return $match;
    }

    /**
     * @param  array<int, list<Category>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function buildNodes(array $byParent, int $parentId, PublicCatalogContextData $context, int $depth): array
    {
        if ($depth > (int) config('catalog.public.max_category_depth', 12)) {
            return [];
        }

        $nodes = [];
        foreach ($byParent[$parentId] ?? [] as $category) {
            $node = $this->summary($category, $context);
            $node['children'] = $this->buildNodes($byParent, (int) $category->id, $context, $depth + 1);
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Category $category, PublicCatalogContextData $context): array
    {
        $translation = $category->translation($context->locale);
        $slug = (string) $translation?->slug;

        return [
            'id' => $category->id,
            'name' => $translation?->name,
            'slug' => $slug,
            'path' => $this->urls->categoryPath($slug),
            'sort_order' => $category->sort_order,
        ];
    }

    /**
     * @param  list<int>  $categoryIds
     * @return array<int, int>
     */
    private function productCounts(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rows = PublicCatalogProductProjection::query()
            ->selectRaw('category_product.category_id as category_id, COUNT(DISTINCT public_catalog_product_projections.product_id) as product_count')
            ->join('category_product', 'category_product.product_id', '=', 'public_catalog_product_projections.product_id')
            ->where('public_catalog_product_projections.is_public', true)
            ->whereIn('category_product.category_id', $categoryIds)
            ->groupBy('category_product.category_id')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->getAttribute('category_id')] = (int) $row->getAttribute('product_count');
        }

        return $counts;
    }
}
