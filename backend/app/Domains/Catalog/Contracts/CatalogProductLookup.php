<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Contracts;

use App\Domains\Catalog\DTOs\CatalogSellableRefData;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface CatalogProductLookup
{
    public function sellableRef(int $variantId): ?CatalogSellableRefData;

    /**
     * @throws ModelNotFoundException
     */
    public function existingSellableRef(int $variantId): CatalogSellableRefData;

    /**
     * @return list<CatalogSellableRefData>
     */
    public function sellableRefsForProduct(int $productId): array;

    public function existsProduct(int $productId): bool;

    public function existsVariant(int $variantId): bool;

    public function existsCategory(int $categoryId): bool;

    public function existsBrand(int $brandId): bool;

    /**
     * @return list<int>
     */
    public function sampleActiveVariantIds(int $limit = 5): array;
}
