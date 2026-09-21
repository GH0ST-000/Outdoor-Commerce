<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Queries;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Exceptions\PublicCatalogNotFoundException;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogUrlGenerator;
use App\Domains\Catalog\PublicApi\Services\PublicSeoComposer;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetPublicBrandsQuery
{
    public function __construct(
        private readonly PublicCatalogUrlGenerator $urls,
        private readonly PublicSeoComposer $seo,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Brand>
     */
    public function paginate(PublicCatalogContextData $context, int $page, int $perPage): LengthAwarePaginator
    {
        unset($context);

        return Brand::query()
            ->where('status', CatalogStatus::Active->value)
            ->whereNull('deleted_at')
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Brand $brand, PublicCatalogContextData $context, bool $detail = false): array
    {
        $translation = $brand->translation($context->locale);
        $usedFallback = $translation !== null && $translation->locale !== $context->locale;
        $slug = (string) $translation?->slug;
        $count = $this->productCount((int) $brand->id);

        $payload = [
            'id' => $brand->id,
            'name' => $translation?->name,
            'slug' => $slug,
            'path' => $this->urls->brandPath($slug),
            'is_featured' => $brand->is_featured,
            'product_count' => $count,
            'used_fallback' => $usedFallback,
        ];

        if (! $detail) {
            return $payload;
        }

        $localeSlugs = [];
        foreach (CatalogLocales::all() as $locale) {
            $localeSlugs[$locale] = $brand->translations->firstWhere('locale', $locale)?->slug;
        }

        return array_merge($payload, [
            'description' => null,
            'website_url' => null,
            'canonical_path' => $this->urls->brandPath($slug),
            'alternate_locale_paths' => $this->urls->alternateLocalePaths($localeSlugs, 'brand'),
            'seo' => $this->seo->compose(
                title: $translation !== null ? (string) $translation->name : '',
                description: null,
                canonicalPath: $this->urls->brandPath($slug),
                alternatePaths: $this->urls->alternateLocalePaths($localeSlugs, 'brand'),
            ),
        ]);
    }

    public function resolve(PublicCatalogContextData $context, string $slug): Brand
    {
        $brand = Brand::query()
            ->where('status', CatalogStatus::Active->value)
            ->whereHas('translations', function ($query) use ($context, $slug): void {
                $query->where('slug', $slug)->whereIn('locale', CatalogLocales::slugLookupLocales($context->locale));
            })
            ->with('translations')
            ->first();

        if ($brand === null) {
            throw PublicCatalogNotFoundException::brand();
        }

        return $brand;
    }

    private function productCount(int $brandId): int
    {
        return (int) PublicCatalogProductProjection::query()
            ->join('products', 'products.id', '=', 'public_catalog_product_projections.product_id')
            ->where('public_catalog_product_projections.is_public', true)
            ->where('products.brand_id', $brandId)
            ->whereNull('products.deleted_at')
            ->count();
    }
}
