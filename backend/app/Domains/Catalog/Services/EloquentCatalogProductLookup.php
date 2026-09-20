<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Contracts\CatalogProductLookup;
use App\Domains\Catalog\DTOs\CatalogSellableRefData;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentCatalogProductLookup implements CatalogProductLookup
{
    public function sellableRef(int $variantId): ?CatalogSellableRefData
    {
        $variant = $this->variantQuery()->find($variantId);

        if ($variant === null || $variant->product === null) {
            return null;
        }

        return $this->toRef($variant, $variant->product);
    }

    public function existingSellableRef(int $variantId): CatalogSellableRefData
    {
        $variant = $this->variantQuery()->find($variantId);

        if ($variant === null || $variant->product === null) {
            throw (new ModelNotFoundException)->setModel(ProductVariant::class, [$variantId]);
        }

        return $this->toRef($variant, $variant->product);
    }

    public function sellableRefsForProduct(int $productId): array
    {
        $product = Product::query()
            ->withTrashed()
            ->with(['categories', 'brand' => static fn ($query) => $query->withTrashed()])
            ->find($productId);

        if ($product === null) {
            return [];
        }

        return ProductVariant::query()
            ->withTrashed()
            ->where('product_id', $productId)
            ->get()
            ->map(fn (ProductVariant $variant): CatalogSellableRefData => $this->toRef($variant, $product))
            ->values()
            ->all();
    }

    public function existsProduct(int $productId): bool
    {
        return Product::query()->whereKey($productId)->exists();
    }

    public function existsVariant(int $variantId): bool
    {
        return ProductVariant::query()->whereKey($variantId)->exists();
    }

    public function existsCategory(int $categoryId): bool
    {
        return Category::query()->whereKey($categoryId)->exists();
    }

    public function existsBrand(int $brandId): bool
    {
        return Brand::query()->whereKey($brandId)->exists();
    }

    public function sampleActiveVariantIds(int $limit = 5): array
    {
        return ProductVariant::query()
            ->active()
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return Builder<ProductVariant>
     */
    private function variantQuery()
    {
        return ProductVariant::query()
            ->withTrashed()
            ->with([
                'product' => static fn ($query) => $query
                    ->withTrashed()
                    ->with(['categories', 'brand' => static fn ($brand) => $brand->withTrashed()]),
            ]);
    }

    private function toRef(ProductVariant $variant, Product $product): CatalogSellableRefData
    {
        $brand = $product->brand;

        return new CatalogSellableRefData(
            variantId: $variant->id,
            productId: $product->id,
            brandId: $product->brand_id,
            isDefault: $variant->is_default,
            variantActive: ! $variant->trashed() && $variant->status === ProductVariantStatus::Active,
            variantDeleted: $variant->trashed(),
            productDeleted: $product->trashed(),
            brandDeleted: $product->brand_id !== null && ($brand === null || $brand->trashed()),
            categoryIds: $product->categories->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all(),
        );
    }
}
