<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Data;

/**
 * Validated, bounded product-list filters. Unknown keys are rejected before this is built.
 */
final readonly class PublicProductListFilterData
{
    /**
     * @param  list<string>  $brands
     * @param  array<string, list<string>>  $attributes
     */
    public function __construct(
        public ?string $category = null,
        public array $brands = [],
        public array $attributes = [],
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public ?bool $inStock = null,
        public ?bool $onSale = null,
        public ?bool $featured = null,
        public ?string $q = null,
        public string $sort = 'default',
        public int $page = 1,
        public int $perPage = 10,
        public bool $includeDescendants = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toCacheArray(): array
    {
        $attributes = $this->attributes;
        ksort($attributes);
        foreach ($attributes as &$values) {
            $values = array_values($values);
            sort($values);
        }

        $brands = $this->brands;
        sort($brands);

        return [
            'category' => $this->category,
            'brands' => $brands,
            'attributes' => $attributes,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'in_stock' => $this->inStock,
            'on_sale' => $this->onSale,
            'featured' => $this->featured,
            'q' => $this->q,
            'sort' => $this->sort,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'include_descendants' => $this->includeDescendants,
        ];
    }
}
