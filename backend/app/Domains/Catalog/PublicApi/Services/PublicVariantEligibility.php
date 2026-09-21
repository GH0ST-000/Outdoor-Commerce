<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\Variants\VariantReadinessService;
use App\Domains\Catalog\Support\Variants\Sku;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use InvalidArgumentException;

/**
 * Public variants must be active, combination-complete, SKU-assigned, and priced.
 * Unpriced active variants are excluded from storefront selection.
 */
final class PublicVariantEligibility
{
    public function __construct(
        private readonly PublicCatalogPricing $pricing,
        private readonly VariantReadinessService $readiness,
    ) {}

    public function isPublic(ProductVariant $variant, ?int $priceListId = null): bool
    {
        return $this->evaluate($variant, $priceListId)['eligible'];
    }

    /**
     * @return array{eligible: bool, reasons: list<string>}
     */
    public function evaluate(ProductVariant $variant, ?int $priceListId = null): array
    {
        $reasons = [];

        if ($variant->trashed() || $variant->status !== ProductVariantStatus::Active) {
            return ['eligible' => false, 'reasons' => ['variant_not_active']];
        }

        try {
            Sku::assertValid(Sku::normalize($variant->sku));
        } catch (InvalidArgumentException) {
            $reasons[] = 'invalid_sku';
        }

        $readiness = $this->readiness->evaluate($variant);
        if (! $readiness['ready']) {
            $reasons[] = 'combination_incomplete';
        }

        $listId = $priceListId ?? $this->pricing->defaultPublicPriceListId();
        if ($listId === null) {
            $reasons[] = 'unpriced';
        } else {
            $quote = $this->pricing->quoteVariant($variant->id, $listId);
            if ($quote === null) {
                $reasons[] = 'unpriced';
            }
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }
}
