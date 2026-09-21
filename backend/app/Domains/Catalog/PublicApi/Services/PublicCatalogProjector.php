<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogProductProjection;
use App\Domains\Catalog\PublicApi\Models\PublicCatalogVariantProjection;
use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuildable projector. Never writes to catalog, pricing, or inventory authority tables.
 */
final class PublicCatalogProjector
{
    public function __construct(
        private readonly PublicProductEligibility $products,
        private readonly PublicVariantEligibility $variants,
        private readonly PublicCatalogPricing $pricing,
        private readonly PublicInventoryAvailability $inventory,
        private readonly Clock $clock,
    ) {}

    public function refreshVariant(int $variantId): PublicCatalogVariantProjection
    {
        $variant = ProductVariant::query()->withTrashed()->find($variantId);
        $currency = (string) config('catalog.public.currency', 'GEL');

        if ($variant === null) {
            PublicCatalogVariantProjection::query()->where('product_variant_id', $variantId)->delete();

            return new PublicCatalogVariantProjection([
                'product_variant_id' => $variantId,
                'is_public' => false,
            ]);
        }

        $priceListId = $this->pricing->defaultPublicPriceListId($currency);
        $quote = $priceListId !== null ? $this->pricing->quoteVariant($variant->id, $priceListId) : null;
        $availability = $this->inventory->forVariant($variant->id);
        $isPublicVariant = $priceListId !== null && $this->variants->isPublic($variant, $priceListId);
        $productEligible = $priceListId !== null && $variant->product !== null && $this->products->isPublic($variant->product, null, $priceListId);
        $isPublic = $isPublicVariant && $productEligible;

        $row = PublicCatalogVariantProjection::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            [
                'product_id' => $variant->product_id,
                'currency_code' => $currency,
                'base_price_minor' => $quote?->baseAmountMinor,
                'final_price_minor' => $quote?->finalAmountMinor,
                'discount_amount_minor' => $quote?->discountAmountMinor,
                'on_sale' => $quote !== null && $quote->onSale(),
                'available_to_sell' => $availability->availableToSell,
                'is_in_stock' => $availability->isInStock(),
                'is_low_stock' => $availability->isLowStock,
                'is_public' => $isPublic,
                'pricing_signature' => $quote?->signature,
                'pricing_version' => $quote !== null ? $quote->pricingVersion : $this->pricing->cacheVersion(),
                'inventory_version' => $this->inventory->cacheVersion(),
                'projected_at' => $this->clock->now(),
            ],
        );

        $this->refreshProduct((int) $variant->product_id);

        return $row;
    }

    public function refreshProduct(int $productId): PublicCatalogProductProjection
    {
        $currency = (string) config('catalog.public.currency', 'GEL');
        $product = Product::query()->withTrashed()->with(['variants', 'translations', 'brand', 'primaryCategory.parent', 'mediaAttachments.asset', 'variants.mediaAttachments.asset'])->find($productId);

        if ($product === null) {
            PublicCatalogProductProjection::query()->where('product_id', $productId)->delete();
            PublicCatalogVariantProjection::query()->where('product_id', $productId)->delete();

            return new PublicCatalogProductProjection([
                'product_id' => $productId,
                'is_public' => false,
            ]);
        }

        $priceListId = $this->pricing->defaultPublicPriceListId($currency);
        $publicRows = PublicCatalogVariantProjection::query()
            ->where('product_id', $productId)
            ->where('currency_code', $currency)
            ->where('is_public', true)
            ->get();

        $publicCount = $publicRows->count();
        $inStockCount = $publicRows->where('is_in_stock', true)->count();
        $finals = $publicRows->pluck('final_price_minor')->filter(static fn ($v): bool => $v !== null);
        $bases = $publicRows->pluck('base_price_minor')->filter(static fn ($v): bool => $v !== null);

        $defaultId = null;
        $default = $product->variants->first(static fn ($variant): bool => (bool) $variant->is_default);
        if ($default !== null && $publicRows->contains(fn ($row): bool => (int) $row->product_variant_id === (int) $default->id)) {
            $defaultId = (int) $default->id;
        } elseif ($publicRows->isNotEmpty()) {
            $defaultId = (int) $publicRows->sortBy('product_variant_id')->first()?->product_variant_id;
        }

        $eligible = $priceListId !== null && $this->products->isPublic($product, null, $priceListId) && $publicCount > 0;

        return PublicCatalogProductProjection::query()->updateOrCreate(
            [
                'product_id' => $productId,
                'currency_code' => $currency,
            ],
            [
                'minimum_base_price_minor' => $bases->isEmpty() ? null : (int) $bases->min(),
                'maximum_base_price_minor' => $bases->isEmpty() ? null : (int) $bases->max(),
                'minimum_final_price_minor' => $finals->isEmpty() ? null : (int) $finals->min(),
                'maximum_final_price_minor' => $finals->isEmpty() ? null : (int) $finals->max(),
                'public_variant_count' => $publicCount,
                'in_stock_variant_count' => $inStockCount,
                'is_in_stock' => $inStockCount > 0,
                'is_on_sale' => $publicRows->contains(fn ($row): bool => (bool) $row->on_sale),
                'is_public' => $eligible,
                'default_variant_id' => $defaultId,
                'pricing_version' => $this->pricing->cacheVersion(),
                'inventory_version' => $this->inventory->cacheVersion(),
                'projected_at' => $this->clock->now(),
            ],
        );
    }

    /**
     * Refresh every variant for a product, then the product row.
     */
    public function refreshProductGraph(int $productId): void
    {
        $ids = ProductVariant::query()->withTrashed()->where('product_id', $productId)->pluck('id');
        foreach ($ids as $variantId) {
            $this->refreshVariantWithoutProduct((int) $variantId);
        }
        $this->refreshProduct($productId);
    }

    public function refreshVariantWithoutProduct(int $variantId): PublicCatalogVariantProjection
    {
        $variant = ProductVariant::query()->withTrashed()->find($variantId);
        $currency = (string) config('catalog.public.currency', 'GEL');

        if ($variant === null) {
            PublicCatalogVariantProjection::query()->where('product_variant_id', $variantId)->delete();

            return new PublicCatalogVariantProjection([
                'product_variant_id' => $variantId,
                'is_public' => false,
            ]);
        }

        $priceListId = $this->pricing->defaultPublicPriceListId($currency);
        $quote = $priceListId !== null ? $this->pricing->quoteVariant($variant->id, $priceListId) : null;
        $availability = $this->inventory->forVariant($variant->id);
        $isPublicVariant = $priceListId !== null && $this->variants->isPublic($variant, $priceListId);
        $product = $variant->product;
        $productEligible = $priceListId !== null && $product !== null && $this->products->isPublic($product, null, $priceListId);

        return PublicCatalogVariantProjection::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            [
                'product_id' => $variant->product_id,
                'currency_code' => $currency,
                'base_price_minor' => $quote?->baseAmountMinor,
                'final_price_minor' => $quote?->finalAmountMinor,
                'discount_amount_minor' => $quote?->discountAmountMinor,
                'on_sale' => $quote !== null && $quote->onSale(),
                'available_to_sell' => $availability->availableToSell,
                'is_in_stock' => $availability->isInStock(),
                'is_low_stock' => $availability->isLowStock,
                'is_public' => $isPublicVariant && $productEligible,
                'pricing_signature' => $quote?->signature,
                'pricing_version' => $quote !== null ? $quote->pricingVersion : $this->pricing->cacheVersion(),
                'inventory_version' => $this->inventory->cacheVersion(),
                'projected_at' => $this->clock->now(),
            ],
        );
    }

    public function refreshBrand(int $brandId): void
    {
        Product::query()->where('brand_id', $brandId)->orderBy('id')->chunkById(100, function ($products): void {
            foreach ($products as $product) {
                $this->refreshProductGraph((int) $product->id);
            }
        });
    }

    public function refreshCategory(int $categoryId): void
    {
        Product::query()
            ->where('primary_category_id', $categoryId)
            ->orWhereIn('id', function ($query) use ($categoryId): void {
                $query->select('product_id')->from('category_product')->where('category_id', $categoryId);
            })
            ->orderBy('id')
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    $this->refreshProductGraph((int) $product->id);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logFailure(string $action, array $context, Throwable $e): void
    {
        Log::error('public_catalog.projection_failed', [
            'module' => 'catalog',
            'action' => $action,
            'status' => 'failed',
            ...$context,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
