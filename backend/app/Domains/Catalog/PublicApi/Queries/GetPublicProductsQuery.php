<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Queries;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Exceptions\PublicCatalogQueryException;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Data\PublicProductListFilterData;
use App\Domains\Catalog\PublicApi\Enums\PublicProductSort;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use App\Domains\Catalog\PublicApi\Services\CategoryDescendantResolver;
use App\Domains\Catalog\PublicApi\Services\PublicAvailabilityPresenter;
use App\Domains\Catalog\PublicApi\Services\PublicCatalogUrlGenerator;
use App\Domains\Catalog\PublicApi\Services\PublicMediaPresenter;
use App\Domains\Catalog\PublicApi\Services\PublicPricePresenter;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GetPublicProductsQuery
{
    public function __construct(
        private readonly CategoryDescendantResolver $descendants,
        private readonly PublicMediaPresenter $media,
        private readonly PublicPricePresenter $prices,
        private readonly PublicAvailabilityPresenter $availability,
        private readonly PublicCatalogUrlGenerator $urls,
    ) {}

    /**
     * @return list<int>
     */
    public function matchingIds(PublicCatalogContextData $context, PublicProductListFilterData $filters): array
    {
        $currency = $context->currency;
        $query = Product::query()
            ->select('products.id')
            ->join('public_catalog_product_projections as pcpp', function ($join) use ($currency): void {
                $join->on('pcpp.product_id', '=', 'products.id')
                    ->where('pcpp.currency_code', '=', $currency)
                    ->where('pcpp.is_public', '=', true);
            })
            ->whereNull('products.deleted_at');

        $this->applyFilters($query, $context, $filters);

        return $query->orderBy('products.id')->pluck('products.id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return array{paginator: LengthAwarePaginator<int, Product>, cards: list<array<string, mixed>>}
     */
    public function paginate(PublicCatalogContextData $context, PublicProductListFilterData $filters): array
    {
        $started = microtime(true);
        $currency = $context->currency;

        $query = Product::query()
            ->select([
                'products.id',
                'products.brand_id',
                'products.primary_category_id',
                'products.is_featured',
                'products.sort_order',
                'products.published_at',
                'products.created_at',
                'products.updated_at',
            ])
            ->join('public_catalog_product_projections as pcpp', function ($join) use ($currency): void {
                $join->on('pcpp.product_id', '=', 'products.id')
                    ->where('pcpp.currency_code', '=', $currency)
                    ->where('pcpp.is_public', '=', true);
            })
            ->whereNull('products.deleted_at');

        $this->applyFilters($query, $context, $filters);
        $this->applySort($query, $context, $filters);

        $paginator = $query
            ->with([
                'translations' => fn ($q) => $q->select(['id', 'product_id', 'locale', 'name', 'slug']),
                'brand.translations' => fn ($q) => $q->select(['id', 'brand_id', 'locale', 'name', 'slug']),
                'primaryCategory.translations' => fn ($q) => $q->select(['id', 'category_id', 'locale', 'name', 'slug']),
                'readyMediaAttachments.asset.derivatives',
                'readyMediaAttachments.translations',
            ])
            ->paginate($filters->perPage, ['products.id'], 'page', $filters->page);

        $ids = collect($paginator->items())->map(fn (Product $product): int => (int) $product->id)->all();
        $projections = PublicCatalogProductProjection::query()
            ->whereIn('product_id', $ids)
            ->where('currency_code', $currency)
            ->get()
            ->keyBy('product_id');

        $cards = [];
        foreach ($paginator->items() as $product) {
            $projection = $projections->get($product->id);
            $cards[] = $this->card($product, $context, $projection);
        }

        Log::info('public_catalog.product_list', [
            'module' => 'catalog',
            'action' => 'product_list',
            'endpoint' => 'products.index',
            'locale' => $context->locale,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'result_count' => count($cards),
            'query_signature' => hash('sha256', (string) json_encode($filters->toCacheArray())),
        ]);

        return ['paginator' => $paginator, 'cards' => $cards];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function cardsByOrderedIds(PublicCatalogContextData $context, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $currency = $context->currency;
        $products = Product::query()
            ->select([
                'products.id',
                'products.brand_id',
                'products.primary_category_id',
                'products.is_featured',
                'products.sort_order',
                'products.published_at',
                'products.created_at',
                'products.updated_at',
            ])
            ->join('public_catalog_product_projections as pcpp', function ($join) use ($currency): void {
                $join->on('pcpp.product_id', '=', 'products.id')
                    ->where('pcpp.currency_code', '=', $currency)
                    ->where('pcpp.is_public', '=', true);
            })
            ->whereIn('products.id', $ids)
            ->whereNull('products.deleted_at')
            ->with([
                'translations' => fn ($q) => $q->select(['id', 'product_id', 'locale', 'name', 'slug']),
                'brand.translations' => fn ($q) => $q->select(['id', 'brand_id', 'locale', 'name', 'slug']),
                'primaryCategory.translations' => fn ($q) => $q->select(['id', 'category_id', 'locale', 'name', 'slug']),
                'readyMediaAttachments.asset.derivatives',
                'readyMediaAttachments.translations',
            ])
            ->get()
            ->keyBy('id');

        $projections = PublicCatalogProductProjection::query()
            ->whereIn('product_id', $ids)
            ->where('currency_code', $currency)
            ->get()
            ->keyBy('product_id');

        $cards = [];
        foreach ($ids as $id) {
            $product = $products->get($id);
            if ($product === null) {
                continue;
            }
            $cards[] = $this->card($product, $context, $projections->get($id));
        }

        return $cards;
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyFilters(Builder $query, PublicCatalogContextData $context, PublicProductListFilterData $filters): void
    {
        if ($filters->category !== null) {
            $categoryIds = $this->resolveCategoryIds($context, $filters->category, $filters->includeDescendants);
            $query->whereExists(function ($sub) use ($categoryIds): void {
                $sub->selectRaw('1')
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'products.id')
                    ->whereIn('category_product.category_id', $categoryIds);
            });
        }

        if ($filters->brands !== []) {
            $brandIds = $this->resolveBrandIds($context, $filters->brands);
            $query->whereIn('products.brand_id', $brandIds);
        }

        if ($filters->featured === true) {
            $query->where('products.is_featured', true);
        }

        if ($filters->inStock === true) {
            $query->where('pcpp.is_in_stock', true);
        }

        if ($filters->onSale === true) {
            $query->where('pcpp.is_on_sale', true);
        }

        if ($filters->minPrice !== null || $filters->maxPrice !== null) {
            $query->whereExists(function ($sub) use ($filters, $context): void {
                $sub->selectRaw('1')
                    ->from('public_catalog_variant_projections as pcvp')
                    ->whereColumn('pcvp.product_id', 'products.id')
                    ->where('pcvp.is_public', true)
                    ->where('pcvp.currency_code', $context->currency);
                if ($filters->minPrice !== null) {
                    $sub->where('pcvp.final_price_minor', '>=', $filters->minPrice);
                }
                if ($filters->maxPrice !== null) {
                    $sub->where('pcvp.final_price_minor', '<=', $filters->maxPrice);
                }
            });
        }

        if ($filters->attributes !== []) {
            $this->applyAttributeFilters($query, $context, $filters->attributes);
        }

        if ($filters->q !== null) {
            $this->applySearch($query, $context, $filters->q);
        }
    }

    /**
     * A product matches only when one public variant satisfies every selected attribute group.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, list<string>>  $attributes
     */
    private function applyAttributeFilters(Builder $query, PublicCatalogContextData $context, array $attributes): void
    {
        $groups = [];
        foreach ($attributes as $code => $valueCodes) {
            $attribute = Attribute::query()
                ->where('code', $code)
                ->where('status', AttributeStatus::Active->value)
                ->where('is_filterable', true)
                ->whereNull('deleted_at')
                ->first();

            if ($attribute === null) {
                throw PublicCatalogQueryException::invalidFilter("Attribute [{$code}] is not filterable.");
            }

            $values = AttributeValue::query()
                ->where('attribute_id', $attribute->id)
                ->whereIn('code', $valueCodes)
                ->where('status', AttributeValueStatus::Active->value)
                ->whereNull('deleted_at')
                ->pluck('id', 'code');

            if ($values->count() !== count($valueCodes)) {
                throw PublicCatalogQueryException::invalidFilter("One or more values for [{$code}] are invalid.");
            }

            $groups[] = [
                'attribute_id' => (int) $attribute->id,
                'value_ids' => $values->values()->map(fn ($id): int => (int) $id)->all(),
            ];
        }

        $query->whereExists(function ($sub) use ($groups, $context): void {
            $sub->selectRaw('1')
                ->from('public_catalog_variant_projections as pcvp')
                ->whereColumn('pcvp.product_id', 'products.id')
                ->where('pcvp.is_public', true)
                ->where('pcvp.currency_code', $context->currency);

            foreach ($groups as $index => $group) {
                $alias = 'pvav_'.$index;
                $sub->join("product_variant_attribute_values as {$alias}", function ($join) use ($alias, $group): void {
                    $join->on("{$alias}.product_variant_id", '=', 'pcvp.product_variant_id')
                        ->where("{$alias}.attribute_id", '=', $group['attribute_id'])
                        ->whereIn("{$alias}.attribute_value_id", $group['value_ids']);
                });
            }
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySearch(Builder $query, PublicCatalogContextData $context, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';
        $locales = array_values(array_unique([$context->locale, $context->fallbackLocale]));

        $query->where(function (Builder $builder) use ($like, $locales): void {
            $builder->whereExists(function ($sub) use ($like, $locales): void {
                $sub->selectRaw('1')
                    ->from('product_translations')
                    ->whereColumn('product_translations.product_id', 'products.id')
                    ->whereIn('product_translations.locale', $locales)
                    ->where(function ($inner) use ($like): void {
                        $inner->where('product_translations.name', 'like', $like)
                            ->orWhere('product_translations.slug', 'like', $like);
                    });
            })->orWhereExists(function ($sub) use ($like): void {
                $sub->selectRaw('1')
                    ->from('product_variants')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->whereNull('product_variants.deleted_at')
                    ->where(function ($inner) use ($like): void {
                        $inner->where('product_variants.sku', 'like', $like)
                            ->orWhere('product_variants.barcode', 'like', $like);
                    });
            })->orWhereExists(function ($sub) use ($like, $locales): void {
                $sub->selectRaw('1')
                    ->from('brands')
                    ->join('brand_translations', 'brand_translations.brand_id', '=', 'brands.id')
                    ->whereColumn('brands.id', 'products.brand_id')
                    ->whereNull('brands.deleted_at')
                    ->whereIn('brand_translations.locale', $locales)
                    ->where('brand_translations.name', 'like', $like);
            });
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, PublicCatalogContextData $context, PublicProductListFilterData $filters): void
    {
        $sort = PublicProductSort::tryFrom($filters->sort) ?? PublicProductSort::Default;

        match ($sort) {
            PublicProductSort::PriceAsc => $query->orderBy('pcpp.minimum_final_price_minor')->orderBy('products.id'),
            PublicProductSort::PriceDesc => $query->orderByDesc('pcpp.minimum_final_price_minor')->orderBy('products.id'),
            PublicProductSort::Newest => $query->orderByDesc('products.published_at')->orderByDesc('products.id'),
            PublicProductSort::NameAsc, PublicProductSort::NameDesc => $this->orderByName($query, $context, $sort === PublicProductSort::NameDesc),
            PublicProductSort::Featured => $query->orderByDesc('products.is_featured')->orderBy('products.sort_order')->orderBy('products.id'),
            PublicProductSort::Default => $query->orderByDesc('products.is_featured')->orderBy('products.sort_order')->orderByDesc('products.id'),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function orderByName(Builder $query, PublicCatalogContextData $context, bool $desc): void
    {
        $query->leftJoin('product_translations as pt_sort', function ($join) use ($context): void {
            $join->on('pt_sort.product_id', '=', 'products.id')
                ->where('pt_sort.locale', '=', $context->locale);
        })->leftJoin('product_translations as pt_fb', function ($join) use ($context): void {
            $join->on('pt_fb.product_id', '=', 'products.id')
                ->where('pt_fb.locale', '=', $context->fallbackLocale);
        });

        $column = DB::raw('COALESCE(pt_sort.name, pt_fb.name)');
        $desc ? $query->orderByDesc($column) : $query->orderBy($column);
        $query->orderBy('products.id');
    }

    /**
     * @return list<int>
     */
    private function resolveCategoryIds(PublicCatalogContextData $context, string $category, bool $includeDescendants): array
    {
        $row = Category::query()
            ->where('status', CatalogStatus::Active->value)
            ->where(function ($query) use ($category, $context): void {
                if (ctype_digit($category)) {
                    $query->where('id', (int) $category);
                }
                $query->orWhereHas('translations', function ($translations) use ($category, $context): void {
                    $translations->where('slug', $category)
                        ->whereIn('locale', CatalogLocales::slugLookupLocales($context->locale));
                });
            })
            ->first();

        if ($row === null) {
            throw PublicCatalogQueryException::invalidFilter('The selected category is not public.');
        }

        return $includeDescendants
            ? $this->descendants->publicIdsIncludingSelf((int) $row->id)
            : [(int) $row->id];
    }

    /**
     * @param  list<string>  $brands
     * @return list<int>
     */
    private function resolveBrandIds(PublicCatalogContextData $context, array $brands): array
    {
        $ids = Brand::query()
            ->where('status', CatalogStatus::Active->value)
            ->where(function ($query) use ($brands, $context): void {
                $numeric = array_values(array_filter($brands, 'ctype_digit'));
                $slugs = array_values(array_filter($brands, fn (string $value): bool => ! ctype_digit($value)));
                if ($numeric !== []) {
                    $query->whereIn('id', array_map('intval', $numeric));
                }
                if ($slugs !== []) {
                    $query->orWhereHas('translations', function ($translations) use ($slugs, $context): void {
                        $translations->whereIn('slug', $slugs)
                            ->whereIn('locale', CatalogLocales::slugLookupLocales($context->locale));
                    });
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            throw PublicCatalogQueryException::invalidFilter('One or more selected brands are not public.');
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Product $product, PublicCatalogContextData $context, ?PublicCatalogProductProjection $projection): array
    {
        $translation = $product->translation($context->locale);
        $brand = $product->brand;
        $category = $product->primaryCategory;
        $primary = null;
        try {
            $primary = $this->media->primaryForProduct($product, $context->locale);
        } catch (Throwable) {
            $primary = null;
        }

        $minFinal = $projection?->minimum_final_price_minor;
        $maxFinal = $projection?->maximum_final_price_minor;
        $onSale = $projection !== null && $projection->is_on_sale;

        return [
            'id' => $product->id,
            'name' => $translation?->name,
            'slug' => $translation?->slug,
            'href' => $this->urls->productPath((string) $translation?->slug),
            'brand' => $brand ? [
                'id' => $brand->id,
                'name' => $brand->localizedName($context->locale),
                'slug' => $brand->translation($context->locale)?->slug,
            ] : null,
            'primary_category' => $category ? [
                'id' => $category->id,
                'name' => $category->localizedName($context->locale),
                'slug' => $category->translation($context->locale)?->slug,
            ] : null,
            'primary_media' => $primary,
            'price' => $this->prices->productRange(
                $context->currency,
                $minFinal !== null ? (int) $minFinal : null,
                $maxFinal !== null ? (int) $maxFinal : null,
                $projection?->minimum_base_price_minor !== null ? (int) $projection->minimum_base_price_minor : null,
                $onSale,
            ),
            'availability' => $this->availability->fromProjection(
                true,
                $projection !== null && $projection->is_in_stock,
                false,
                $minFinal !== null,
            ),
            'is_featured' => $product->is_featured,
            'variant_count' => $projection !== null ? (int) $projection->public_variant_count : 0,
            'default_variant_id' => $projection?->default_variant_id !== null
                ? (int) $projection->default_variant_id
                : null,
            'used_fallback' => $translation !== null && $translation->locale !== $context->locale,
        ];
    }
}
