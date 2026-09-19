<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Variants;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * Keeps exactly one default variant per product among non-archived variants.
 *
 * Every method mutates rows and must run inside the caller's transaction while
 * the product row is locked.
 */
final class DefaultVariantService
{
    public function setDefault(Product $product, ProductVariant $variant): ProductVariant
    {
        if ((int) $variant->product_id !== (int) $product->id) {
            throw ValidationException::withMessages([
                'variant' => ['This variant does not belong to the product.'],
            ]);
        }

        if ($variant->trashed() || ! $variant->status->canBeDefault()) {
            throw ValidationException::withMessages([
                'is_default' => ['Archived variants cannot be the default.'],
            ]);
        }

        ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereKeyNot($variant->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        if (! $variant->is_default) {
            $variant->is_default = true;
            $variant->save();
        }

        return $variant;
    }

    /**
     * Collapses the product to exactly one default among its non-archived variants.
     */
    public function ensureSingleDefault(Product $product): ?ProductVariant
    {
        $candidates = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereIn('status', [ProductVariantStatus::Draft->value, ProductVariantStatus::Active->value])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(static fn (ProductVariant $variant): int => $variant->status === ProductVariantStatus::Active ? 0 : 1)
            ->values();

        if ($candidates->isEmpty()) {
            ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            return null;
        }

        $preferred = $candidates->firstWhere('is_default', true) ?? $candidates->first();

        return $preferred instanceof ProductVariant ? $this->setDefault($product, $preferred) : null;
    }

    /**
     * Validates and applies the replacement required when archiving the default variant.
     */
    public function replaceDefaultForArchive(
        Product $product,
        ProductVariant $archiving,
        ?int $replacementVariantId,
    ): ?ProductVariant {
        if (! $archiving->is_default) {
            return null;
        }

        $hasOtherVariants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereKeyNot($archiving->id)
            ->whereIn('status', [ProductVariantStatus::Draft->value, ProductVariantStatus::Active->value])
            ->exists();

        if (! $hasOtherVariants) {
            $archiving->is_default = false;

            return null;
        }

        if ($replacementVariantId === null) {
            throw ValidationException::withMessages([
                'replacement_variant_id' => ['A replacement variant is required when archiving the default variant.'],
            ]);
        }

        /** @var ProductVariant|null $replacement */
        $replacement = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereKeyNot($archiving->id)
            ->whereKey($replacementVariantId)
            ->lockForUpdate()
            ->first();

        if ($replacement === null) {
            throw ValidationException::withMessages([
                'replacement_variant_id' => ['The replacement variant must be another variant of this product.'],
            ]);
        }

        if (! $replacement->status->canBeDefault()) {
            throw ValidationException::withMessages([
                'replacement_variant_id' => ['Archived variants cannot be the default.'],
            ]);
        }

        $archiving->is_default = false;
        $archiving->save();

        return $this->setDefault($product, $replacement);
    }

    /**
     * Locks the product row so default bookkeeping is serialized per product.
     */
    public function lockProduct(Product $product): Product
    {
        /** @var Product $locked */
        $locked = Product::withTrashed()
            ->whereKey($product->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }
}
