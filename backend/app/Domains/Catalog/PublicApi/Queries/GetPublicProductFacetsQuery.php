<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Queries;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Data\PublicProductListFilterData;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogVariantProjection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bounded facets over the currently filtered public product set.
 * Counts respect current filters. Day 16 replaces this with Meilisearch facets.
 */
final class GetPublicProductFacetsQuery
{
    public function __construct(
        private readonly GetPublicProductsQuery $products,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(PublicCatalogContextData $context, PublicProductListFilterData $filters): array
    {
        $productIds = $this->matchingProductIds($context, $filters);

        $priceRange = PublicCatalogVariantProjection::query()
            ->where('is_public', true)
            ->where('currency_code', $context->currency)
            ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
            ->selectRaw('MIN(final_price_minor) as min_price, MAX(final_price_minor) as max_price')
            ->first();

        $inStock = PublicCatalogProductProjection::query()
            ->where('is_public', true)
            ->where('currency_code', $context->currency)
            ->where('is_in_stock', true)
            ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
            ->count();

        $onSale = PublicCatalogProductProjection::query()
            ->where('is_public', true)
            ->where('currency_code', $context->currency)
            ->where('is_on_sale', true)
            ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
            ->count();

        return [
            'brands' => $this->brands($context, $productIds),
            'categories' => $this->categories($context, $productIds),
            'attributes' => $this->attributes($context, $productIds),
            'price_range' => [
                'currency' => $context->currency,
                'min_final_amount_minor' => $priceRange !== null && $priceRange->getAttribute('min_price') !== null
                    ? (int) $priceRange->getAttribute('min_price')
                    : null,
                'max_final_amount_minor' => $priceRange !== null && $priceRange->getAttribute('max_price') !== null
                    ? (int) $priceRange->getAttribute('max_price')
                    : null,
            ],
            'in_stock_count' => $inStock,
            'on_sale_count' => $onSale,
        ];
    }

    /**
     * @return list<int>|null
     */
    private function matchingProductIds(PublicCatalogContextData $context, PublicProductListFilterData $filters): ?array
    {
        $unfiltered = $filters->category === null
            && $filters->brands === []
            && $filters->attributes === []
            && $filters->minPrice === null
            && $filters->maxPrice === null
            && $filters->inStock === null
            && $filters->onSale === null
            && $filters->featured === null
            && $filters->q === null;

        if ($unfiltered) {
            return null;
        }

        return $this->products->matchingIds($context, $filters);
    }

    /**
     * @param  list<int>|null  $productIds
     * @return list<array<string, mixed>>
     */
    private function brands(PublicCatalogContextData $context, ?array $productIds): array
    {
        $query = Brand::query()
            ->where('status', CatalogStatus::Active->value)
            ->with('translations')
            ->withCount(['products as public_product_count' => function (Builder $builder) use ($productIds): void {
                $builder->whereIn('id', PublicCatalogProductProjection::query()->select('product_id')->where('is_public', true));
                if ($productIds !== null) {
                    $builder->whereIn('id', $productIds);
                }
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(40);

        return $query->get()
            ->filter(fn (Brand $brand): bool => (int) $brand->getAttribute('public_product_count') > 0)
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'code' => $brand->translation($context->locale)?->slug,
                'name' => $brand->localizedName($context->locale),
                'count' => (int) $brand->getAttribute('public_product_count'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>|null  $productIds
     * @return list<array<string, mixed>>
     */
    private function categories(PublicCatalogContextData $context, ?array $productIds): array
    {
        $categories = Category::query()
            ->where('status', CatalogStatus::Active->value)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(40)
            ->get();

        $out = [];
        foreach ($categories as $category) {
            $count = PublicCatalogProductProjection::query()
                ->where('is_public', true)
                ->whereIn('product_id', function ($sub) use ($category, $productIds): void {
                    $sub->select('product_id')->from('category_product')->where('category_id', $category->id);
                    if ($productIds !== null) {
                        $sub->whereIn('product_id', $productIds);
                    }
                })
                ->count();
            if ($count === 0) {
                continue;
            }
            $out[] = [
                'id' => $category->id,
                'code' => $category->translation($context->locale)?->slug,
                'name' => $category->localizedName($context->locale),
                'count' => $count,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>|null  $productIds
     * @return list<array<string, mixed>>
     */
    private function attributes(PublicCatalogContextData $context, ?array $productIds): array
    {
        $attributes = Attribute::query()
            ->where('status', AttributeStatus::Active->value)
            ->where('is_filterable', true)
            ->whereNull('deleted_at')
            ->with(['translations', 'values' => fn ($q) => $q->where('status', AttributeValueStatus::Active->value)->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id'), 'values.translations'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(12)
            ->get();

        $out = [];
        foreach ($attributes as $attribute) {
            $values = [];
            foreach ($attribute->values as $value) {
                $count = PublicCatalogVariantProjection::query()
                    ->where('is_public', true)
                    ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
                    ->whereIn('product_variant_id', function ($sub) use ($value): void {
                        $sub->select('product_variant_id')
                            ->from('product_variant_attribute_values')
                            ->where('attribute_value_id', $value->id);
                    })
                    ->distinct()
                    ->count('product_id');

                if ($count === 0) {
                    continue;
                }

                $values[] = [
                    'code' => $value->code,
                    'name' => $value->localizedName($context->locale) ?? $value->code,
                    'color_hex' => $value->color_hex,
                    'count' => $count,
                ];
            }

            if ($values === []) {
                continue;
            }

            $out[] = [
                'code' => $attribute->code,
                'name' => $attribute->localizedName($context->locale) ?? $attribute->code,
                'values' => $values,
            ];
        }

        return $out;
    }
}
