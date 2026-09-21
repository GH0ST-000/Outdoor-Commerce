<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Listeners;

use App\Domains\Catalog\Events\CatalogAttributeChanged;
use App\Domains\Catalog\Events\CatalogBrandChanged;
use App\Domains\Catalog\Events\CatalogCategoryChanged;
use App\Domains\Catalog\Events\CatalogMediaChanged;
use App\Domains\Catalog\Events\CatalogProductChanged;
use App\Domains\Catalog\Events\CatalogVariantChanged;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Inventory\Events\InventoryAdjusted;
use App\Domains\Inventory\Events\InventoryCountReconciled;
use App\Domains\Inventory\Events\InventoryOutOfStock;
use App\Domains\Inventory\Events\InventoryReceived;
use App\Domains\Inventory\Events\InventoryReservationCancelled;
use App\Domains\Inventory\Events\InventoryReservationCommitted;
use App\Domains\Inventory\Events\InventoryReservationExpired;
use App\Domains\Inventory\Events\InventoryReservationReleased;
use App\Domains\Inventory\Events\InventoryReserved;
use App\Domains\Inventory\Events\InventoryTransferred;
use App\Domains\Inventory\Models\InventoryLedgerEntry;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Pricing\Events\PriceCancelled;
use App\Domains\Pricing\Events\PriceChanged;
use App\Domains\Pricing\Events\PricePublished;
use App\Domains\Pricing\Events\PromotionActivated;
use App\Domains\Pricing\Events\PromotionPaused;
use App\Domains\Pricing\Events\PromotionTargetsChanged;
use App\Domains\Pricing\Models\PricePeriod;
use App\Jobs\RefreshPromotionCatalogProjections;
use App\Jobs\RefreshPublicProductProjection;
use App\Jobs\RefreshPublicVariantProjection;
use Illuminate\Support\Facades\DB;

final class RefreshPublicCatalogProjections
{
    public function __construct(
        private readonly CatalogCache $catalogCache,
    ) {}

    public function productChanged(CatalogProductChanged $event): void
    {
        $this->afterCommit(fn () => RefreshPublicProductProjection::dispatch($event->productId));
    }

    public function variantChanged(CatalogVariantChanged $event): void
    {
        $this->afterCommit(fn () => RefreshPublicVariantProjection::dispatch($event->variantId));
    }

    public function brandChanged(CatalogBrandChanged $event): void
    {
        $this->afterCommit(function () use ($event): void {
            Product::query()->where('brand_id', $event->brandId)->orderBy('id')->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    RefreshPublicProductProjection::dispatch((int) $product->id);
                }
            });
        });
    }

    public function categoryChanged(CatalogCategoryChanged $event): void
    {
        $this->afterCommit(function () use ($event): void {
            Product::query()
                ->where('primary_category_id', $event->categoryId)
                ->orWhereIn('id', function ($query) use ($event): void {
                    $query->select('product_id')->from('category_product')->where('category_id', $event->categoryId);
                })
                ->orderBy('id')
                ->chunkById(100, function ($products): void {
                    foreach ($products as $product) {
                        RefreshPublicProductProjection::dispatch((int) $product->id);
                    }
                });
        });
    }

    public function attributeChanged(CatalogAttributeChanged $event): void
    {
        $this->afterCommit(function () use ($event): void {
            $productIds = ProductVariant::query()
                ->whereIn('id', function ($query) use ($event): void {
                    $query->select('product_variant_id')
                        ->from('product_variant_attribute_values')
                        ->where('attribute_id', $event->attributeId);
                })
                ->pluck('product_id')
                ->unique();

            foreach ($productIds as $productId) {
                RefreshPublicProductProjection::dispatch((int) $productId);
            }
        });
    }

    public function mediaChanged(CatalogMediaChanged $event): void
    {
        $this->afterCommit(function () use ($event): void {
            if ($event->variantId !== null) {
                RefreshPublicVariantProjection::dispatch($event->variantId);
            }
            if ($event->productId !== null) {
                RefreshPublicProductProjection::dispatch($event->productId);
            }
        });
    }

    public function inventoryVariant(int $variantId): void
    {
        $this->afterCommit(fn () => RefreshPublicVariantProjection::dispatch($variantId));
    }

    public function inventoryReserved(InventoryReserved $event): void
    {
        $this->inventoryVariant($event->productVariantId);
    }

    public function inventoryReservationReleased(InventoryReservationReleased $event): void
    {
        $this->inventoryVariant($event->productVariantId);
    }

    public function inventoryReservationCancelled(InventoryReservationCancelled $event): void
    {
        $this->afterCommit(function () use ($event): void {
            $variantId = InventoryReservation::query()->whereKey($event->reservationId)->value('product_variant_id');
            if ($variantId !== null) {
                RefreshPublicVariantProjection::dispatch((int) $variantId);
            }
        });
    }

    public function inventoryReservationExpired(InventoryReservationExpired $event): void
    {
        $this->inventoryVariant($event->productVariantId);
    }

    public function inventoryReservationCommitted(InventoryReservationCommitted $event): void
    {
        $this->inventoryVariant($event->productVariantId);
    }

    public function inventoryAdjusted(InventoryAdjusted $event): void
    {
        $this->inventoryVariant($event->productVariantId);
    }

    public function inventoryReconciled(InventoryCountReconciled $event): void
    {
        $this->inventoryVariant($event->productVariantId);
    }

    public function inventoryTransferred(InventoryTransferred $event): void
    {
        $this->afterCommit(function () use ($event): void {
            $ids = InventoryLedgerEntry::query()
                ->where('inventory_operation_id', $event->operationId)
                ->pluck('product_variant_id')
                ->unique();
            foreach ($ids as $variantId) {
                RefreshPublicVariantProjection::dispatch((int) $variantId);
            }
        });
    }

    public function inventoryReceived(InventoryReceived $event): void
    {
        $this->afterCommit(function () use ($event): void {
            $ids = InventoryLedgerEntry::query()
                ->where('inventory_operation_id', $event->operationId)
                ->pluck('product_variant_id')
                ->unique();
            foreach ($ids as $variantId) {
                RefreshPublicVariantProjection::dispatch((int) $variantId);
            }
        });
    }

    public function inventoryOutOfStock(InventoryOutOfStock $event): void
    {
        $this->inventoryVariant($event->productVariantId);
    }

    public function priceChanged(PriceChanged $event): void
    {
        $this->afterCommit(fn () => RefreshPublicVariantProjection::dispatch($event->variantId));
    }

    public function pricePublished(PricePublished $event): void
    {
        $this->afterCommit(function () use ($event): void {
            $period = PricePeriod::query()->with('variantPrice')->find($event->pricePeriodId);
            $variantId = $period?->variantPrice?->product_variant_id;
            if ($variantId !== null) {
                RefreshPublicVariantProjection::dispatch((int) $variantId);
            }
        });
    }

    public function priceCancelled(PriceCancelled $event): void
    {
        $this->pricePublished(new PricePublished($event->pricePeriodId, $event->variantPriceId));
    }

    public function promotionChanged(PromotionActivated|PromotionPaused|PromotionTargetsChanged $event): void
    {
        $this->afterCommit(fn () => RefreshPromotionCatalogProjections::dispatch($event->promotionId));
    }

    private function afterCommit(callable $callback): void
    {
        DB::afterCommit(function () use ($callback): void {
            $this->catalogCache->bump();
            $callback();
        });
    }
}
