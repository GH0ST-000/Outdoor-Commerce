<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Catalog\Support\CatalogSlug;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;

/**
 * Central public-readiness rules. List and detail must share this specification.
 */
final class PublicProductEligibility
{
    public function __construct(
        private readonly PublicCatalogPricing $pricing,
        private readonly PublicVariantEligibility $variants,
    ) {}

    public function isPublic(Product $product, ?string $locale = null, ?int $priceListId = null): bool
    {
        return $this->evaluate($product, $locale, $priceListId)['eligible'];
    }

    /**
     * @return array{eligible: bool, reasons: list<string>}
     */
    public function evaluate(Product $product, ?string $locale = null, ?int $priceListId = null): array
    {
        $locale ??= CatalogLocales::default();
        $reasons = [];

        if ($product->trashed() || $product->status !== ProductStatus::Active) {
            return ['eligible' => false, 'reasons' => ['product_not_active']];
        }

        $product->loadMissing(['translations', 'brand', 'primaryCategory', 'variants']);

        $translation = $this->resolvedTranslation($product, $locale);
        if ($translation === null || trim($translation->name) === '' || ! CatalogSlug::isValid((string) $translation->slug)) {
            $reasons[] = 'missing_localized_slug';
        }

        if ($product->primary_category_id === null || $product->primaryCategory === null || $product->primaryCategory->trashed()) {
            $reasons[] = 'missing_primary_category';
        } elseif (! $this->categoryAncestryIsPublic($product->primaryCategory)) {
            $reasons[] = 'inactive_category_ancestry';
        }

        if ($product->brand_id !== null) {
            $brand = $product->brand;
            if ($brand === null || $brand->trashed() || $brand->status !== CatalogStatus::Active) {
                $reasons[] = 'inactive_brand';
            }
        }

        $listId = $priceListId ?? $this->pricing->defaultPublicPriceListId();
        if ($listId === null) {
            $reasons[] = 'no_public_price_list';
        } else {
            $publicVariants = $product->variants
                ->filter(fn ($variant): bool => $variant->status === ProductVariantStatus::Active && ! $variant->trashed())
                ->filter(fn ($variant): bool => $this->variants->isPublic($variant, $listId));

            if ($publicVariants->isEmpty()) {
                $reasons[] = 'no_public_variant';
            }
        }

        if ($this->requiresReadyMedia() && ! $this->hasReadyImage($product)) {
            $reasons[] = 'missing_ready_media';
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    public function resolvedTranslation(Product $product, string $locale): mixed
    {
        $product->loadMissing('translations');
        $fallback = CatalogLocales::fallback();

        return $product->translations->firstWhere('locale', $locale)
            ?? $product->translations->firstWhere('locale', $fallback);
    }

    public function categoryAncestryIsPublic(Category $category): bool
    {
        $current = $category;
        $seen = [];
        $max = (int) config('catalog.public.max_category_depth', 12);

        for ($i = 0; $i < $max && $current !== null; $i++) {
            if (isset($seen[$current->id])) {
                return false;
            }
            $seen[$current->id] = true;

            if ($current->trashed() || $current->status !== CatalogStatus::Active) {
                return false;
            }

            if ($current->parent_id === null) {
                return true;
            }

            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return false;
    }

    public function requiresReadyMedia(): bool
    {
        return (bool) config('catalog.public.require_ready_media', true);
    }

    public function hasReadyImage(Product $product): bool
    {
        $product->loadMissing(['mediaAttachments.asset', 'variants.mediaAttachments.asset']);

        $productReady = $product->mediaAttachments
            ->contains(static fn (MediaAttachment $attachment): bool => $attachment->asset?->status === MediaStatus::Ready);

        if ($productReady) {
            return true;
        }

        foreach ($product->variants as $variant) {
            if ($variant->mediaAttachments->contains(static fn (MediaAttachment $attachment): bool => $attachment->asset?->status === MediaStatus::Ready)) {
                return true;
            }
        }

        return false;
    }
}
