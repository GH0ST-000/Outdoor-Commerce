<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Data;

final readonly class CatalogVariantSearchDocument
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<int>  $categoryAncestorIds
     * @param  list<string>  $attributeCodes
     * @param  list<string>  $attributeValueCodes
     * @param  list<string>  $attributeFilterKeys
     * @param  list<string>  $searchAliases
     */
    public function __construct(
        public string $id,
        public int $productId,
        public int $variantId,
        public string $locale,
        public string $slug,
        public string $name,
        public string $searchableName,
        public string $shortDescription,
        public ?int $brandId,
        public string $brandName,
        public string $brandSlug,
        public ?int $categoryId,
        public array $categoryIds,
        public array $categoryAncestorIds,
        public string $categoryName,
        public string $sku,
        public array $attributeCodes,
        public array $attributeValueCodes,
        public array $attributeFilterKeys,
        public ?int $priceMinor,
        public ?int $compareAtPriceMinor,
        public string $currency,
        public ?int $discountPercentage,
        public bool $isInStock,
        public bool $isOnSale,
        public bool $isFeatured,
        public bool $isNew,
        public int $publicationTimestamp,
        public int $createdTimestamp,
        public int $merchandisingPriority,
        public int $popularityScore,
        public ?int $primaryMediaId,
        public string $primaryMediaAlt,
        public array $searchAliases,
        public string $documentVersion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'locale' => $this->locale,
            'slug' => $this->slug,
            'name' => $this->name,
            'searchable_name' => $this->searchableName,
            'short_description' => $this->shortDescription,
            'brand_id' => $this->brandId,
            'brand_name' => $this->brandName,
            'brand_slug' => $this->brandSlug,
            'category_id' => $this->categoryId,
            'category_ids' => $this->categoryIds,
            'category_ancestor_ids' => $this->categoryAncestorIds,
            'category_name' => $this->categoryName,
            'sku' => $this->sku,
            'attribute_codes' => $this->attributeCodes,
            'attribute_value_codes' => $this->attributeValueCodes,
            'attribute_filter_keys' => $this->attributeFilterKeys,
            'price_minor' => $this->priceMinor,
            'compare_at_price_minor' => $this->compareAtPriceMinor,
            'currency' => $this->currency,
            'discount_percentage' => $this->discountPercentage,
            'is_in_stock' => $this->isInStock,
            'is_on_sale' => $this->isOnSale,
            'is_featured' => $this->isFeatured,
            'is_new' => $this->isNew,
            'publication_timestamp' => $this->publicationTimestamp,
            'created_timestamp' => $this->createdTimestamp,
            'merchandising_priority' => $this->merchandisingPriority,
            'popularity_score' => $this->popularityScore,
            'primary_media_id' => $this->primaryMediaId,
            'primary_media_alt' => $this->primaryMediaAlt,
            'search_aliases' => $this->searchAliases,
            'document_version' => $this->documentVersion,
            'name_sort' => $this->searchableName,
        ];
    }
}
