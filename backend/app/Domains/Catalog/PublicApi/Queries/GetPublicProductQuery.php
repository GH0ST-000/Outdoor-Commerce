<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Queries;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Exceptions\PublicCatalogNotFoundException;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use App\Domains\Catalog\PublicApi\Services\PublicAvailabilityPresenter;
use App\Domains\Catalog\PublicApi\Services\PublicBreadcrumbBuilder;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogUrlGenerator;
use App\Domains\Catalog\PublicApi\Services\PublicMediaPresenter;
use App\Domains\Catalog\PublicApi\Services\PublicPricePresenter;
use App\Domains\Catalog\PublicApi\Services\PublicProductEligibility;
use App\Domains\Catalog\PublicApi\Services\PublicSeoComposer;
use App\Domains\Catalog\PublicApi\Services\PublicVariantEligibility;
use App\Domains\Catalog\PublicApi\Services\PublicVariantMatrixBuilder;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class GetPublicProductQuery
{
    public function __construct(
        private readonly PublicProductEligibility $eligibility,
        private readonly PublicVariantEligibility $variantEligibility,
        private readonly PublicCatalogPricing $pricing,
        private readonly PublicInventoryAvailability $inventory,
        private readonly PublicMediaPresenter $media,
        private readonly PublicPricePresenter $prices,
        private readonly PublicAvailabilityPresenter $availability,
        private readonly PublicBreadcrumbBuilder $breadcrumbs,
        private readonly PublicSeoComposer $seo,
        private readonly PublicCatalogUrlGenerator $urls,
        private readonly PublicVariantMatrixBuilder $matrix,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function find(PublicCatalogContextData $context, string $slug): array
    {
        $started = microtime(true);

        $product = Product::query()
            ->whereHas('translations', function ($query) use ($context, $slug): void {
                $query->where('slug', $slug)->whereIn('locale', CatalogLocales::slugLookupLocales($context->locale));
            })
            ->with([
                'translations',
                'brand.translations',
                'primaryCategory.translations',
                'primaryCategory.parent.translations',
                'categories.translations',
                'variants.combinationRows.attributeValue.translations',
                'variants.combinationRows.attribute.translations',
                'variantAttributes.translations',
                'variantAttributes.values.translations',
                'readyMediaAttachments.asset.derivatives',
                'readyMediaAttachments.translations',
                'variants.readyMediaAttachments.asset.derivatives',
                'variants.readyMediaAttachments.translations',
            ])
            ->first();

        if ($product === null || ! $this->eligibility->isPublic($product, $context->locale, $context->priceListId)) {
            throw PublicCatalogNotFoundException::product();
        }

        $translation = $this->eligibility->resolvedTranslation($product, $context->locale);
        $usedFallback = $translation !== null && $translation->locale !== $context->locale;

        $publicVariants = $product->variants
            ->filter(fn (ProductVariant $variant): bool => $this->variantEligibility->isPublic($variant, $context->priceListId))
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();

        $variantIds = $publicVariants->map(fn (ProductVariant $variant): int => (int) $variant->id)->all();
        $quotes = $this->pricing->quoteVariants($variantIds, $context->priceListId, $context->effectiveAt);
        $availability = $this->inventory->forVariants($variantIds);

        $projection = PublicCatalogProductProjection::query()
            ->where('product_id', $product->id)
            ->where('currency_code', $context->currency)
            ->first();

        $defaultId = $this->resolveDefaultVariantId($publicVariants, $projection?->default_variant_id);

        $finals = [];
        $bases = [];
        $onSale = false;
        $inStock = false;
        foreach ($publicVariants as $variant) {
            $quote = $quotes[$variant->id] ?? null;
            if ($quote !== null) {
                $finals[] = $quote->finalAmountMinor;
                $bases[] = $quote->baseAmountMinor;
                $onSale = $onSale || $quote->onSale();
            }
            $stock = $availability[$variant->id] ?? null;
            $inStock = $inStock || ($stock !== null && $stock->isInStock());
        }

        $localeSlugs = [];
        foreach (CatalogLocales::all() as $locale) {
            $localeSlugs[$locale] = $product->translations->firstWhere('locale', $locale)?->slug;
        }

        $gallery = $this->media->galleryForProduct($product, $context->locale);
        $brand = $product->brand;
        $brandPublic = $brand !== null && ! $brand->trashed() && $brand->status === CatalogStatus::Active;
        $primary = $product->primaryCategory;

        $publicCategories = $product->categories
            ->filter(fn ($category): bool => $this->eligibility->categoryAncestryIsPublic($category))
            ->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->localizedName($context->locale),
                'slug' => $category->translation($context->locale)?->slug,
            ])
            ->values()
            ->all();

        $payload = [
            'id' => $product->id,
            'name' => $translation?->name,
            'slug' => $translation?->slug,
            'short_description' => $translation?->short_description,
            'description' => $translation?->description,
            'model_number' => $product->model_number,
            'is_featured' => $product->is_featured,
            'used_fallback' => $usedFallback,
            'brand' => $brandPublic ? [
                'id' => $brand->id,
                'name' => $brand->localizedName($context->locale),
                'slug' => $brand->translation($context->locale)?->slug,
                'path' => $this->urls->brandPath((string) $brand->translation($context->locale)?->slug),
            ] : null,
            'primary_category' => $primary !== null && $this->eligibility->categoryAncestryIsPublic($primary) ? [
                'id' => $primary->id,
                'name' => $primary->localizedName($context->locale),
                'slug' => $primary->translation($context->locale)?->slug,
                'path' => $this->urls->categoryPath((string) $primary->translation($context->locale)?->slug),
            ] : null,
            'categories' => $publicCategories,
            'breadcrumbs' => $primary !== null ? $this->breadcrumbs->forCategory($primary, $context->locale) : [],
            'gallery' => $gallery,
            'price' => $this->prices->productRange(
                $context->currency,
                $finals === [] ? null : min($finals),
                $finals === [] ? null : max($finals),
                $bases === [] ? null : min($bases),
                $onSale,
            ),
            'availability' => $this->availability->fromProjection(true, $inStock, false, $finals !== []),
            'variants' => $this->matrix->build(
                $product,
                $publicVariants->all(),
                $quotes,
                $availability,
                $context,
                $defaultId,
            ),
            'default_variant_id' => $defaultId,
            'seo' => $this->seo->compose(
                title: (string) ($translation?->seo_title ?: $translation?->name),
                description: $translation?->seo_description ?: $this->seo->fallbackDescription($translation?->short_description),
                canonicalPath: $this->urls->productPath((string) $translation?->slug),
                alternatePaths: $this->urls->alternateLocalePaths($localeSlugs, 'product'),
                openGraphMedia: $gallery[0] ?? null,
            ),
            'canonical_path' => $this->urls->productPath((string) $translation?->slug),
            'alternate_locale_paths' => $this->urls->alternateLocalePaths($localeSlugs, 'product'),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];

        Log::info('public_catalog.product_detail', [
            'module' => 'catalog',
            'action' => 'product_detail',
            'endpoint' => 'products.show',
            'locale' => $context->locale,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'result_count' => 1,
            'entity_id' => $product->id,
        ]);

        return $payload;
    }

    /**
     * @param  Collection<int, ProductVariant>  $publicVariants
     */
    private function resolveDefaultVariantId($publicVariants, mixed $projectedDefaultId): ?int
    {
        $ids = $publicVariants->map(fn (ProductVariant $variant): int => (int) $variant->id);
        if ($projectedDefaultId !== null && $ids->contains((int) $projectedDefaultId)) {
            return (int) $projectedDefaultId;
        }

        $marked = $publicVariants->first(fn (ProductVariant $variant): bool => $variant->is_default);
        if ($marked !== null) {
            return (int) $marked->id;
        }

        return $ids->first();
    }
}
