<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\Exceptions\CartException;
use App\Domains\Catalog\Contracts\CatalogProductLookup;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;

final class CartValidationService
{
    public function __construct(
        private readonly CatalogProductLookup $catalog,
        private readonly PublicCatalogPricing $pricing,
        private readonly PublicInventoryAvailability $availability,
    ) {}

    public function maxLineQuantity(): int
    {
        return max(1, (int) config('cart.max_line_quantity', 12));
    }

    public function assertQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw CartException::quantityLimitExceeded();
        }

        if ($quantity > $this->maxLineQuantity()) {
            throw CartException::quantityLimitExceeded();
        }
    }

    public function sellableVariant(int $variantId, ?int $productId, string $currency, int $priceListId): ProductVariant
    {
        $ref = $this->catalog->sellableRef($variantId);
        if ($ref === null || $ref->variantDeleted || $ref->productDeleted || ! $ref->variantActive) {
            throw CartException::variantUnavailable();
        }

        if ($productId !== null && $ref->productId !== $productId) {
            throw CartException::variantProductMismatch();
        }

        $variant = ProductVariant::query()->with('product')->find($variantId);
        if ($variant === null || $variant->status !== ProductVariantStatus::Active) {
            throw CartException::variantUnavailable();
        }

        $product = $variant->product;
        if (! $product instanceof Product
            || $product->status !== ProductStatus::Active
            || $product->published_at === null
        ) {
            throw CartException::productUnavailable();
        }

        if ($this->pricing->quoteVariant($variantId, $priceListId) === null) {
            throw CartException::productUnavailable();
        }

        return $variant;
    }

    public function assertStockForIncrease(int $variantId, int $desiredQuantity): void
    {
        $stock = $this->availability->forVariant($variantId);
        $allowed = min($this->maxLineQuantity(), max(0, $stock->availableToSell));

        if ($desiredQuantity > $allowed) {
            if ($stock->availableToSell <= 0) {
                throw CartException::insufficientStock();
            }
            if ($desiredQuantity > $this->maxLineQuantity()) {
                throw CartException::quantityLimitExceeded();
            }
            throw CartException::insufficientStock();
        }
    }

    public function assertCurrency(CartActorData $actor, string $cartCurrency): void
    {
        if (strtoupper($actor->currency) !== strtoupper($cartCurrency)) {
            throw CartException::currencyMismatch();
        }
    }
}
