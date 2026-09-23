<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Services;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogVariantProjection;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogUrlGenerator;
use App\Domains\Catalog\Search\Data\BrandSearchDocument;
use App\Domains\Catalog\Search\Data\CatalogVariantSearchDocument;
use App\Domains\Catalog\Search\Data\CategorySearchDocument;
use App\Domains\Catalog\Search\Support\SearchTextNormalizer;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Support\Carbon;

final class SearchDocumentFactory
{
    public function __construct(
        private readonly SearchTextNormalizer $normalizer,
        private readonly PublicCatalogUrlGenerator $urls,
    ) {}

    public function variantDocumentId(int $variantId): string
    {
        return 'var_'.$variantId;
    }

    public function categoryDocumentId(int $categoryId): string
    {
        return 'cat_'.$categoryId;
    }

    public function brandDocumentId(int $brandId): string
    {
        return 'brand_'.$brandId;
    }

    /**
     * @return list<CatalogVariantSearchDocument>
     */
    public function variantDocumentsForProduct(int $productId): array
    {
        $currency = (string) config('catalog.public.currency', 'GEL');
        $product = Product::query()
            ->with([
                'translations',
                'brand.translations',
                'primaryCategory.translations',
                'categories.translations',
                'readyMediaAttachments.translations',
                'variants' => fn ($q) => $q->with(['attributeValues.attribute', 'attributeValues.translations', 'readyMediaAttachments.translations']),
            ])
            ->find($productId);

        if ($product === null) {
            return [];
        }

        $projections = PublicCatalogVariantProjection::query()
            ->where('product_id', $productId)
            ->where('currency_code', $currency)
            ->where('is_public', true)
            ->get()
            ->keyBy('product_variant_id');

        if ($projections->isEmpty()) {
            return [];
        }

        $documents = [];
        foreach (CatalogLocales::all() as $locale) {
            foreach ($product->variants as $variant) {
                $projection = $projections->get($variant->id);
                if ($projection === null) {
                    continue;
                }
                $document = $this->variantDocument($product, $variant, $projection, $locale);
                if ($document !== null) {
                    $documents[] = $document;
                }
            }
        }

        return $documents;
    }

    /**
     * @return list<string>
     */
    public function variantDocumentIdsForProduct(int $productId): array
    {
        $ids = PublicCatalogVariantProjection::query()
            ->where('product_id', $productId)
            ->pluck('product_variant_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $variantIds = ProductVariant::query()->withTrashed()->where('product_id', $productId)->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $ids = array_values(array_unique(array_merge($ids, $variantIds)));
        $documentIds = [];
        foreach ($ids as $id) {
            foreach (CatalogLocales::all() as $locale) {
                $documentIds[] = $this->variantDocumentId((int) $id).'_'.$locale;
            }
        }

        return $documentIds;
    }

    public function variantDocument(
        Product $product,
        ProductVariant $variant,
        PublicCatalogVariantProjection $projection,
        string $locale,
    ): ?CatalogVariantSearchDocument {
        $translation = $product->translation($locale);
        if ($translation === null || $translation->name === null || $translation->slug === null) {
            return null;
        }

        $brand = $product->brand;
        $category = $product->primaryCategory;
        $categoryIds = $product->categories->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($product->primary_category_id !== null && ! in_array((int) $product->primary_category_id, $categoryIds, true)) {
            $categoryIds[] = (int) $product->primary_category_id;
        }

        $ancestorIds = [];
        foreach ($product->categories as $assigned) {
            $ancestorIds = array_merge($ancestorIds, $this->ancestorIds($assigned));
        }
        if ($category !== null) {
            $ancestorIds = array_merge($ancestorIds, $this->ancestorIds($category));
        }
        $ancestorIds = array_values(array_unique($ancestorIds));

        $attributeCodes = [];
        $attributeValueCodes = [];
        $filterKeys = [];
        foreach ($variant->attributeValues as $value) {
            $code = $value->attribute?->code;
            if (! is_string($code) || $code === '') {
                continue;
            }
            $attributeCodes[] = $code;
            $attributeValueCodes[] = $value->code;
            $filterKeys[] = 'attr_'.$code.'_'.$value->code;
        }

        $media = $variant->readyMediaAttachments->first() ?? $product->readyMediaAttachments->first();
        $published = $product->published_at instanceof Carbon ? $product->published_at : Carbon::parse((string) $product->published_at);
        $created = $product->created_at instanceof Carbon ? $product->created_at : now();
        $isNew = $published->greaterThan(now()->subDays(30));

        $base = $projection->base_price_minor !== null ? (int) $projection->base_price_minor : null;
        $final = $projection->final_price_minor !== null ? (int) $projection->final_price_minor : null;
        $discount = null;
        if ($base !== null && $final !== null && $base > 0 && $final < $base) {
            $discount = (int) round((($base - $final) / $base) * 100);
        }

        $aliases = array_values(array_filter([
            $product->model_number,
            $variant->barcode,
            $brand?->localizedName($locale === 'ka' ? 'en' : 'ka'),
        ], fn ($value): bool => is_string($value) && $value !== ''));

        $payload = [
            'product_id' => (int) $product->id,
            'variant_id' => (int) $variant->id,
            'locale' => $locale,
            'slug' => $translation->slug,
            'sku' => $variant->sku,
            'price' => $final,
            'stock' => $projection->is_in_stock,
            'sale' => $projection->on_sale,
            'featured' => $product->is_featured,
            'filters' => $filterKeys,
            'categories' => $categoryIds,
        ];

        return new CatalogVariantSearchDocument(
            id: $this->variantDocumentId((int) $variant->id).'_'.$locale,
            productId: (int) $product->id,
            variantId: (int) $variant->id,
            locale: $locale,
            slug: $translation->slug,
            name: $this->normalizer->normalize($translation->name, 200),
            searchableName: $this->normalizer->searchable($translation->name),
            shortDescription: $this->normalizer->normalize($translation->short_description, 240),
            brandId: $brand?->id !== null ? (int) $brand->id : null,
            brandName: $this->normalizer->normalize($brand?->localizedName($locale), 120),
            brandSlug: (string) ($brand?->translation($locale)->slug ?? ''),
            categoryId: $category?->id !== null ? (int) $category->id : null,
            categoryIds: $categoryIds,
            categoryAncestorIds: $ancestorIds,
            categoryName: $this->normalizer->normalize($category?->localizedName($locale), 120),
            sku: $variant->sku,
            attributeCodes: array_values(array_unique($attributeCodes)),
            attributeValueCodes: array_values(array_unique($attributeValueCodes)),
            attributeFilterKeys: array_values(array_unique($filterKeys)),
            priceMinor: $final,
            compareAtPriceMinor: $base,
            currency: $projection->currency_code,
            discountPercentage: $discount,
            isInStock: (bool) $projection->is_in_stock,
            isOnSale: (bool) $projection->on_sale,
            isFeatured: (bool) $product->is_featured,
            isNew: $isNew,
            publicationTimestamp: $published->getTimestamp(),
            createdTimestamp: $created->getTimestamp(),
            merchandisingPriority: (int) $product->sort_order * -1,
            popularityScore: (int) $projection->available_to_sell,
            primaryMediaId: $media?->media_asset_id !== null ? (int) $media->media_asset_id : null,
            primaryMediaAlt: $this->normalizer->normalize($media?->translation($locale)?->alt_text, 120),
            searchAliases: $aliases,
            documentVersion: hash('sha256', (string) json_encode($payload)),
        );
    }

    /**
     * @return list<CategorySearchDocument>
     */
    public function categoryDocuments(?int $categoryId = null): array
    {
        $query = Category::query()
            ->with('translations')
            ->where('status', CatalogStatus::Active->value)
            ->whereNull('deleted_at');
        if ($categoryId !== null) {
            $query->whereKey($categoryId);
        }

        $documents = [];
        foreach ($query->orderBy('id')->get() as $category) {
            $count = PublicCatalogProductProjection::query()
                ->where('is_public', true)
                ->where('currency_code', (string) config('catalog.public.currency', 'GEL'))
                ->whereIn('product_id', function ($sub) use ($category): void {
                    $sub->select('product_id')->from('category_product')->where('category_id', $category->id);
                })
                ->count();

            foreach (CatalogLocales::all() as $locale) {
                $translation = $category->translation($locale);
                if ($translation === null || $translation->slug === null || $translation->name === null) {
                    continue;
                }
                $documents[] = new CategorySearchDocument(
                    id: $this->categoryDocumentId((int) $category->id).'_'.$locale,
                    categoryId: (int) $category->id,
                    locale: $locale,
                    name: $this->normalizer->normalize($translation->name, 160),
                    slug: $translation->slug,
                    path: $this->urls->categoryPath($translation->slug),
                    productCount: $count,
                    documentVersion: hash('sha256', $category->id.'|'.$locale.'|'.$translation->name.'|'.$count),
                );
            }
        }

        return $documents;
    }

    /**
     * @return list<BrandSearchDocument>
     */
    public function brandDocuments(?int $brandId = null): array
    {
        $query = Brand::query()
            ->with('translations')
            ->where('status', CatalogStatus::Active->value)
            ->whereNull('deleted_at');
        if ($brandId !== null) {
            $query->whereKey($brandId);
        }

        $documents = [];
        foreach ($query->orderBy('id')->get() as $brand) {
            $count = PublicCatalogProductProjection::query()
                ->where('is_public', true)
                ->where('currency_code', (string) config('catalog.public.currency', 'GEL'))
                ->whereIn('product_id', Product::query()->select('id')->where('brand_id', $brand->id))
                ->count();

            foreach (CatalogLocales::all() as $locale) {
                $translation = $brand->translation($locale);
                if ($translation === null || $translation->slug === null || $translation->name === null) {
                    continue;
                }
                $documents[] = new BrandSearchDocument(
                    id: $this->brandDocumentId((int) $brand->id).'_'.$locale,
                    brandId: (int) $brand->id,
                    locale: $locale,
                    name: $this->normalizer->normalize($translation->name, 160),
                    slug: $translation->slug,
                    path: $this->urls->brandPath($translation->slug),
                    productCount: $count,
                    documentVersion: hash('sha256', $brand->id.'|'.$locale.'|'.$translation->name.'|'.$count),
                );
            }
        }

        return $documents;
    }

    /**
     * @return list<int>
     */
    private function ancestorIds(Category $category): array
    {
        $ids = [];
        $current = $category;
        $guard = 0;
        while ($current->parent_id !== null && $guard < 16) {
            $ids[] = (int) $current->parent_id;
            $parent = $current->relationLoaded('parent') ? $current->parent : Category::query()->find($current->parent_id);
            if ($parent === null) {
                break;
            }
            $current = $parent;
            $guard++;
        }

        return $ids;
    }
}
