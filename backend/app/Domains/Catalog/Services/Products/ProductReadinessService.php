<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services\Products;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Exceptions\ProductNotReadyException;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Catalog\Support\CatalogSlug;
use App\Domains\Pricing\Services\PricingReadinessService;

final class ProductReadinessService
{
    public function __construct(
        private readonly PricingReadinessService $pricingReadiness,
    ) {}

    /**
     * Reports readiness without ever mutating the product. Active products that
     * regress (for example after their last variant is archived) surface the same
     * issues as warnings; demotion is always an explicit admin action.
     *
     * `media_warnings` is reported separately and never contributes to `ready`:
     * an imageless product is a merchandising gap, not a broken one, and blocking
     * activation on it would stall launches for no correctness gain.
     *
     * @return array{ready: bool, issues: array<string, list<string>>, issue_count: int, warnings: array<string, list<string>>, media_warnings: array<string, list<string>>, pricing_warnings: array<string, list<string>>}
     */
    public function evaluate(Product $product): array
    {
        $product->loadMissing(['translations', 'categories', 'brand', 'primaryCategory', 'variants']);

        /** @var array<string, list<string>> $issues */
        $issues = [];
        $ka = CatalogLocales::default();
        $translation = $product->translations->firstWhere('locale', $ka);

        if ($translation === null) {
            $issues['translations.ka'] = ['A Georgian translation is required.'];
        } else {
            if (trim($translation->name) === '') {
                $issues['translations.ka.name'] = ['A Georgian name is required.'];
            }
            if (trim($translation->slug) === '' || ! CatalogSlug::isValid($translation->slug)) {
                $issues['translations.ka.slug'] = ['A valid Georgian slug is required.'];
            }
        }

        if ($product->primary_category_id === null) {
            $issues['primary_category_id'] = ['An active primary category is required.'];
        } else {
            $primary = $product->primaryCategory;
            if ($primary === null || $primary->trashed()) {
                $issues['primary_category_id'] = ['The primary category must exist and not be deleted.'];
            } elseif ($primary->status !== CatalogStatus::Active) {
                $issues['primary_category_id'] = ['An active primary category is required.'];
            }

            $assignedIds = $product->categories->pluck('id')->all();
            if (! in_array($product->primary_category_id, $assignedIds, true)) {
                $issues['category_ids'] = ['The primary category must be included in category assignments.'];
            }
        }

        foreach ($product->categories as $category) {
            if ($category->trashed()) {
                $issues['category_ids'] ??= [];
                $issues['category_ids'][] = "Category [{$category->id}] is deleted.";
            }
        }

        if ($product->brand_id !== null) {
            $brand = $product->brand;
            if ($brand === null || $brand->trashed()) {
                $issues['brand_id'] = ['The assigned brand must exist and not be deleted.'];
            } elseif ($brand->status !== CatalogStatus::Active) {
                $issues['brand_id'] = ['An active brand is required for activation.'];
            }
        }

        if ($product->trashed()) {
            $issues['product'] = ['A deleted product cannot be activated.'];
        }

        foreach ($this->variantIssues($product) as $key => $messages) {
            $issues[$key] = $messages;
        }

        $pricingWarnings = $this->pricingReadiness->warningsFor($product);

        return [
            'ready' => $issues === [],
            'issues' => $issues,
            'issue_count' => count($issues),
            'warnings' => $product->status === ProductStatus::Active ? $issues : [],
            'media_warnings' => $this->mediaWarnings($product),
            'pricing_warnings' => $pricingWarnings,
        ];
    }

    /**
     * Advisory media gaps: no processed image anywhere on the product, no primary
     * image, or a primary image without Georgian alt text (the default storefront
     * locale, so its absence is an accessibility regression for every visitor).
     *
     * @return array<string, list<string>>
     */
    public function mediaWarnings(Product $product): array
    {
        $product->loadMissing([
            'mediaAttachments.asset',
            'mediaAttachments.translations',
            'variants.mediaAttachments.asset',
        ]);

        /** @var array<string, list<string>> $warnings */
        $warnings = [];

        $productReady = $product->mediaAttachments
            ->filter(static fn (MediaAttachment $attachment): bool => $attachment->asset?->status === MediaStatus::Ready);

        $variantReadyCount = $product->variants
            ->sum(static fn ($variant): int => $variant->mediaAttachments
                ->filter(static fn (MediaAttachment $attachment): bool => $attachment->asset?->status === MediaStatus::Ready)
                ->count());

        if ($productReady->isEmpty() && $variantReadyCount === 0) {
            $warnings['media.images'] = ['No processed image is attached to this product or any of its variants.'];
        }

        $primary = $productReady->first(static fn (MediaAttachment $attachment): bool => $attachment->is_primary);

        if ($primary === null) {
            $warnings['media.primary'] = ['This product has no processed primary image.'];

            return $warnings;
        }

        if ($primary->altText(CatalogLocales::default()) === null) {
            $warnings['media.primary.alt_text.ka'] = ['The primary image is missing Georgian alt text.'];
        }

        return $warnings;
    }

    /**
     * A product is purchasable-ready only with at least one active variant and
     * exactly one default variant, which must itself be active.
     *
     * @return array<string, list<string>>
     */
    private function variantIssues(Product $product): array
    {
        /** @var array<string, list<string>> $issues */
        $issues = [];

        // The relation excludes soft-deleted variants, so "non-deleted" holds by construction.
        $variants = $product->variants;

        if ($variants->where('status', ProductVariantStatus::Active)->isEmpty()) {
            $issues['variants'] = ['At least one active variant is required.'];
        }

        $defaults = $variants->where('is_default', true)->values();

        if ($defaults->count() === 0) {
            $issues['variants.default'] = ['Exactly one default variant is required.'];

            return $issues;
        }

        if ($defaults->count() > 1) {
            $issues['variants.default'] = ['Only one default variant is allowed.'];

            return $issues;
        }

        $default = $defaults->first();
        if ($default !== null && $default->status !== ProductVariantStatus::Active) {
            $issues['variants.default'] = ['The default variant must be active.'];
        }

        return $issues;
    }

    public function assertReadyForActivation(Product $product): void
    {
        $result = $this->evaluate($product);
        if (! $result['ready']) {
            throw new ProductNotReadyException(
                'Product is not ready for activation.',
                $result['issues'],
            );
        }
    }
}
