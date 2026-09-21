<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Listeners;

use App\Domains\Catalog\Events\CatalogAttributeChanged;
use App\Domains\Catalog\Events\CatalogBrandChanged;
use App\Domains\Catalog\Events\CatalogCategoryChanged;
use App\Domains\Catalog\Events\CatalogMediaChanged;
use App\Domains\Catalog\Events\CatalogProductChanged;
use App\Domains\Catalog\Events\CatalogVariantChanged;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductTranslation;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

final class CatalogProjectionObserver
{
    public function productSaved(Product $product): void
    {
        $this->after(fn () => event(new CatalogProductChanged((int) $product->id)));
    }

    public function productDeleted(Product $product): void
    {
        $this->productSaved($product);
    }

    public function translationSaved(ProductTranslation $translation): void
    {
        $this->after(fn () => event(new CatalogProductChanged((int) $translation->product_id)));
    }

    public function variantSaved(ProductVariant $variant): void
    {
        $this->after(fn () => event(new CatalogVariantChanged((int) $variant->id, (int) $variant->product_id)));
    }

    public function variantDeleted(ProductVariant $variant): void
    {
        $this->variantSaved($variant);
    }

    public function brandSaved(Brand $brand): void
    {
        $this->after(fn () => event(new CatalogBrandChanged((int) $brand->id)));
    }

    public function categorySaved(Category $category): void
    {
        $this->after(fn () => event(new CatalogCategoryChanged((int) $category->id)));
    }

    public function attributeSaved(Attribute $attribute): void
    {
        $this->after(fn () => event(new CatalogAttributeChanged((int) $attribute->id)));
    }

    public function attributeValueSaved(AttributeValue $value): void
    {
        $this->after(fn () => event(new CatalogAttributeChanged((int) $value->attribute_id)));
    }

    public function mediaAttachmentSaved(MediaAttachment $attachment): void
    {
        $this->dispatchMedia($attachment->mediable_type, (int) $attachment->mediable_id);
    }

    public function mediaAssetSaved(MediaAsset $asset): void
    {
        $asset->loadMissing('attachments');
        foreach ($asset->attachments as $attachment) {
            $this->dispatchMedia($attachment->mediable_type, (int) $attachment->mediable_id);
        }
    }

    private function dispatchMedia(string $type, int $id): void
    {
        $this->after(function () use ($type, $id): void {
            if ($type === (new Product)->getMorphClass()) {
                event(new CatalogMediaChanged($id, null));
            } elseif ($type === (new ProductVariant)->getMorphClass()) {
                $productId = ProductVariant::query()->withTrashed()->whereKey($id)->value('product_id');
                event(new CatalogMediaChanged($productId !== null ? (int) $productId : null, $id));
            }
        });
    }

    private function after(callable $callback): void
    {
        DB::afterCommit($callback);
    }
}
