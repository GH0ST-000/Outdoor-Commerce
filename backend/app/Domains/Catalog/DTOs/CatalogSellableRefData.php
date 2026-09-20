<?php

declare(strict_types=1);

namespace App\Domains\Catalog\DTOs;

/**
 * Cross-module snapshot of a sellable variant and its product placement.
 */
final readonly class CatalogSellableRefData
{
    /**
     * @param  list<int>  $categoryIds
     */
    public function __construct(
        public int $variantId,
        public int $productId,
        public ?int $brandId,
        public bool $isDefault,
        public bool $variantActive,
        public bool $variantDeleted,
        public bool $productDeleted,
        public bool $brandDeleted,
        public array $categoryIds,
    ) {}
}
