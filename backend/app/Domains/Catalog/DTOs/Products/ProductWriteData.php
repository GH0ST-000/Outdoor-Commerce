<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Products;

use App\Domains\Catalog\Enums\ProductStatus;

final readonly class ProductWriteData
{
    /**
     * @param  list<ProductTranslationData>  $translations
     * @param  list<int>|null  $categoryIds  null = leave unchanged on update
     */
    public function __construct(
        public ?int $brandId,
        public ?int $primaryCategoryId,
        public ?array $categoryIds,
        public ProductStatus $status,
        public ?string $modelNumber,
        public ?string $manufacturerPartNumber,
        public bool $isFeatured,
        public int $sortOrder,
        public array $translations,
        public bool $syncTranslations,
        public bool $removeEnglish = false,
    ) {}
}
