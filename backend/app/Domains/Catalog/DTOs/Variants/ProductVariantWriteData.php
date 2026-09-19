<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs\Variants;

use App\Domains\Catalog\Enums\ProductVariantStatus;

final readonly class ProductVariantWriteData
{
    /**
     * @param  list<array{attribute_id: int, attribute_value_id: int}>|null  $attributeValues  Null leaves the existing combination untouched.
     */
    public function __construct(
        public ?string $sku,
        public ?string $barcode,
        public ProductVariantStatus $status,
        public int $sortOrder,
        public ?array $attributeValues,
        public bool $barcodeProvided = false,
        public ?bool $isDefault = null,
    ) {}
}
