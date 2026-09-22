<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Support;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Exceptions\PublicCatalogQueryException;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\PublicApi\Data\PublicProductListFilterData;
use App\Domains\Catalog\PublicApi\Enums\PublicProductSort;
use App\Domains\Catalog\PublicApi\Services\CategoryDescendantResolver;
use App\Domains\Catalog\Support\CatalogLocales;

final class SearchFilterBuilder
{
    public function __construct(
        private readonly CategoryDescendantResolver $descendants,
    ) {}

    /**
     * @return list<string>
     */
    public function variantFilters(PublicCatalogContextData $context, PublicProductListFilterData $filters): array
    {
        $parts = ['locale = '.$this->quote($context->locale)];

        if ($filters->category !== null) {
            $ids = $this->resolveCategoryIds($context, $filters->category, $filters->includeDescendants);
            $parts[] = $this->in('category_ids', $ids);
        }

        if ($filters->brands !== []) {
            $ids = $this->resolveBrandIds($context, $filters->brands);
            $parts[] = $this->in('brand_id', $ids);
        }

        if ($filters->featured === true) {
            $parts[] = 'is_featured = true';
        }
        if ($filters->inStock === true) {
            $parts[] = 'is_in_stock = true';
        }
        if ($filters->onSale === true) {
            $parts[] = 'is_on_sale = true';
        }
        if ($filters->minPrice !== null) {
            $parts[] = 'price_minor >= '.$filters->minPrice;
        }
        if ($filters->maxPrice !== null) {
            $parts[] = 'price_minor <= '.$filters->maxPrice;
        }

        foreach ($this->attributeKeys($filters->attributes) as $keys) {
            $parts[] = $this->inStrings('attribute_filter_keys', $keys);
        }

        return $parts;
    }

    /**
     * @return list<string>|null
     */
    public function sort(PublicProductListFilterData $filters): ?array
    {
        $sort = PublicProductSort::tryFrom($filters->sort) ?? PublicProductSort::Default;

        return match ($sort) {
            PublicProductSort::PriceAsc => ['price_minor:asc'],
            PublicProductSort::PriceDesc => ['price_minor:desc'],
            PublicProductSort::Newest => ['publication_timestamp:desc'],
            PublicProductSort::NameAsc => ['name_sort:asc'],
            PublicProductSort::NameDesc => ['name_sort:desc'],
            PublicProductSort::Featured => ['is_featured:desc', 'merchandising_priority:desc'],
            PublicProductSort::Default => null,
        };
    }

    /**
     * @param  array<string, list<string>>  $attributes
     * @return list<list<string>>
     */
    private function attributeKeys(array $attributes): array
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
                ->pluck('code');

            if ($values->count() !== count($valueCodes)) {
                throw PublicCatalogQueryException::invalidFilter("One or more values for [{$code}] are invalid.");
            }

            $groups[] = $values->map(fn ($value): string => 'attr_'.$code.'_'.$value)->all();
        }

        return $groups;
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
     * @param  list<int>  $ids
     */
    private function in(string $field, array $ids): string
    {
        $list = implode(', ', array_map(static fn (int $id): string => (string) $id, $ids));

        return $field.' IN ['.$list.']';
    }

    /**
     * @param  list<string>  $values
     */
    private function inStrings(string $field, array $values): string
    {
        $list = implode(', ', array_map(fn (string $value): string => $this->quote($value), $values));

        return $field.' IN ['.$list.']';
    }

    private function quote(string $value): string
    {
        return '"'.str_replace('"', '\\"', $value).'"';
    }
}
