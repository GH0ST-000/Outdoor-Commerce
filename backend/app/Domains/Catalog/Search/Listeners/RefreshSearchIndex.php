<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Listeners;

use App\Domains\Catalog\Events\CatalogAttributeChanged;
use App\Domains\Catalog\Events\CatalogBrandChanged;
use App\Domains\Catalog\Events\CatalogCategoryChanged;
use App\Domains\Catalog\Events\CatalogMediaChanged;
use App\Domains\Catalog\Events\CatalogProductChanged;
use App\Domains\Catalog\Events\CatalogVariantChanged;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
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
use App\Jobs\SyncBrandSearchDocuments;
use App\Jobs\SyncCategorySearchDocuments;
use App\Jobs\SyncProductSearchDocuments;
use Illuminate\Support\Facades\DB;

final class RefreshSearchIndex
{
    public function productChanged(CatalogProductChanged $event): void
    {
        $this->afterCommit(fn () => SyncProductSearchDocuments::dispatch($event->productId));
    }

    public function variantChanged(CatalogVariantChanged $event): void
    {
        $this->afterCommit(fn () => SyncProductSearchDocuments::dispatch($event->productId));
    }

    public function brandChanged(CatalogBrandChanged $event): void
    {
        $this->afterCommit(function () use ($event): void {
            SyncBrandSearchDocuments::dispatch($event->brandId);
            Product::query()->where('brand_id', $event->brandId)->orderBy('id')->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    SyncProductSearchDocuments::dispatch((int) $product->id);
                }
            });
        });
    }

    public function categoryChanged(CatalogCategoryChanged $event): void
    {
        $this->afterCommit(function () use ($event): void {
            SyncCategorySearchDocuments::dispatch($event->categoryId);
            Product::query()
                ->where('primary_category_id', $event->categoryId)
                ->orWhereIn('id', function ($query) use ($event): void {
                    $query->select('product_id')->from('category_product')->where('category_id', $event->categoryId);
                })
                ->orderBy('id')
                ->chunkById(100, function ($products): void {
                    foreach ($products as $product) {
                        SyncProductSearchDocuments::dispatch((int) $product->id);
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
                SyncProductSearchDocuments::dispatch((int) $productId);
            }
        });
    }

    public function mediaChanged(CatalogMediaChanged $event): void
    {
        if ($event->productId !== null) {
            $this->afterCommit(fn () => SyncProductSearchDocuments::dispatch($event->productId));
        }
    }

    public function inventoryVariant(int $variantId): void
    {
        $this->afterCommit(function () use ($variantId): void {
            $productId = ProductVariant::query()->whereKey($variantId)->value('product_id');
            if ($productId !== null) {
                $seconds = (int) config('search.inventory_debounce_seconds', 15);
                SyncProductSearchDocuments::dispatch((int) $productId, true)
                    ->delay(now()->addSeconds($seconds));
            }
        });
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
                $this->inventoryVariant((int) $variantId);
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
                $this->inventoryVariant((int) $variantId);
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
                $this->inventoryVariant((int) $variantId);
            }
        });
    }

    public function inventoryOutOfStock(InventoryOutOfStock $event): void
    {
        $this->inventoryVariant($event->productVariantId);
    }

    public function priceChanged(PriceChanged $event): void
    {
        $this->inventoryVariant($event->variantId);
    }

    public function pricePublished(PricePublished $event): void
    {
        $this->afterCommit(function () use ($event): void {
            $period = PricePeriod::query()->with('variantPrice')->find($event->pricePeriodId);
            $variantId = $period?->variantPrice?->product_variant_id;
            if ($variantId !== null) {
                $this->inventoryVariant((int) $variantId);
            }
        });
    }

    public function priceCancelled(PriceCancelled $event): void
    {
        $this->pricePublished(new PricePublished($event->pricePeriodId, $event->variantPriceId));
    }

    public function promotionChanged(PromotionActivated|PromotionPaused|PromotionTargetsChanged $event): void
    {
        $this->afterCommit(function (): void {
            Product::query()->orderBy('id')->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    SyncProductSearchDocuments::dispatch((int) $product->id, true);
                }
            });
        });
    }

    private function afterCommit(callable $callback): void
    {
        if (! (bool) config('search.enabled', true)) {
            return;
        }

        DB::afterCommit($callback);
    }
}
