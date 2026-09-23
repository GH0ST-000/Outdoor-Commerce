<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Cart\DTOs\CartIssueData;
use App\Domains\Cart\DTOs\CartLineAvailabilityData;
use App\Domains\Cart\DTOs\CartLinePricingData;
use App\Domains\Cart\DTOs\CartLineSnapshotData;
use App\Domains\Cart\DTOs\CartSnapshotData;
use App\Domains\Cart\Enums\CartIssueCode;
use App\Domains\Cart\Events\CartAvailabilityChanged;
use App\Domains\Cart\Events\CartPriceChanged;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Inventory\DTOs\PublicAvailabilityData;
use App\Domains\Pricing\DTOs\PublicPriceQuoteData;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CartPresenter
{
    public function __construct(
        private readonly CartPricingService $pricing,
        private readonly CartAvailabilityService $availability,
        private readonly Clock $clock,
    ) {}

    public function present(Cart $cart, string $locale, int $priceListId, bool $persistSnapshots = true): CartSnapshotData
    {
        $cart->loadMissing([
            'items.product.translations',
            'items.product.brand.translations',
            'items.variant.attributeValues.translations',
        ]);

        /** @var list<CartItem> $items */
        $items = $cart->items->all();
        $variantIds = array_map(static fn (CartItem $item): int => (int) $item->variant_id, $items);
        $quotes = $this->pricing->quotesFor($variantIds, $priceListId);
        $stock = $this->availability->forVariants($variantIds);

        $lines = [];
        $cartIssues = [];
        $itemCount = 0;
        $subtotal = 0;
        $discount = 0;
        $total = 0;
        $priceEvents = [];
        $availabilityEvents = [];

        foreach ($items as $item) {
            $quote = $quotes[$item->variant_id] ?? null;
            $availability = $stock[$item->variant_id] ?? new PublicAvailabilityData($item->variant_id, 0, false);
            $line = $this->presentLine($item, $locale, $quote, $availability);
            $lines[] = $line;
            $itemCount += $item->quantity;
            $subtotal += $line->pricing->lineSubtotalMinor;
            $discount += $line->pricing->lineDiscountMinor;
            $total += $line->pricing->lineTotalMinor;
            foreach ($line->issues as $issue) {
                $cartIssues[] = $issue;
            }

            if ($persistSnapshots && $quote !== null && $item->unit_price_at_add_minor !== $quote->finalAmountMinor) {
                $priceEvents[] = [
                    $cart->public_id,
                    $item->public_id,
                    $item->unit_price_at_add_minor,
                    $quote->finalAmountMinor,
                ];
                $item->unit_price_at_add_minor = $quote->finalAmountMinor;
                $item->discount_at_add_minor = $quote->discountAmountMinor;
                $item->save();
            }

            $wasAvailable = $item->quantity <= $this->availability->maximumAllowed($availability, $quote !== null);
            if (! $wasAvailable) {
                $availabilityEvents[] = [$cart->public_id, $item->public_id, false];
            }
        }

        $ttlDays = $cart->user_id !== null
            ? (int) config('cart.authenticated_ttl_days', 90)
            : (int) config('cart.guest_ttl_days', 30);
        $now = $this->clock->now();

        $cart->item_count = $itemCount;
        $cart->unique_item_count = count($lines);
        $cart->subtotal_minor = $subtotal;
        $cart->discount_total_minor = $discount;
        $cart->total_minor = $total;
        $cart->last_activity_at = $now;
        $cart->expires_at = $now->addDays($ttlDays);
        $cart->save();

        if ($priceEvents !== [] || $availabilityEvents !== []) {
            DB::afterCommit(function () use ($priceEvents, $availabilityEvents): void {
                foreach ($priceEvents as $event) {
                    event(new CartPriceChanged($event[0], $event[1], $event[2], $event[3]));
                }
                foreach ($availabilityEvents as $event) {
                    event(new CartAvailabilityChanged($event[0], $event[1], $event[2]));
                }
            });
        }

        return new CartSnapshotData(
            publicId: $cart->public_id,
            status: $cart->status,
            version: $cart->version,
            currency: $cart->currency,
            itemCount: $itemCount,
            uniqueItemCount: count($lines),
            items: $lines,
            itemsSubtotalMinor: $subtotal,
            discountTotalMinor: $discount,
            cartTotalMinor: $total,
            issues: $cartIssues,
            updatedAt: $cart->updated_at instanceof CarbonImmutable
                ? $cart->updated_at
                : CarbonImmutable::parse((string) $cart->updated_at),
        );
    }

    public function empty(string $currency): CartSnapshotData
    {
        return CartSnapshotData::empty($currency);
    }

    private function presentLine(
        CartItem $item,
        string $locale,
        ?PublicPriceQuoteData $quote,
        PublicAvailabilityData $availability,
    ): CartLineSnapshotData {
        $issues = [];
        $product = $item->product;
        $variant = $item->variant;
        $productOk = $product !== null
            && $product->status === ProductStatus::Active
            && $product->published_at !== null;
        $variantOk = $variant !== null && $variant->status === ProductVariantStatus::Active;

        if (! $productOk) {
            $issues[] = new CartIssueData(
                CartIssueCode::ProductUnavailable,
                'This product is no longer available.',
                $item->public_id,
            );
        } elseif (! $variantOk) {
            $issues[] = new CartIssueData(
                CartIssueCode::VariantUnavailable,
                'This option is no longer available.',
                $item->public_id,
            );
        }

        $hasPrice = $quote !== null;
        $unit = $hasPrice ? $quote->finalAmountMinor : 0;
        $compare = $hasPrice ? $quote->baseAmountMinor : 0;
        $lineSubtotal = $compare * $item->quantity;
        $lineTotal = $unit * $item->quantity;
        $lineDiscount = max(0, $lineSubtotal - $lineTotal);
        $priceChanged = $hasPrice && $item->unit_price_at_add_minor !== $quote->finalAmountMinor;

        if ($priceChanged && $quote !== null) {
            $code = $quote->finalAmountMinor > $item->unit_price_at_add_minor
                ? CartIssueCode::PriceIncreased
                : CartIssueCode::PriceDecreased;
            $issues[] = new CartIssueData(
                $code,
                $code === CartIssueCode::PriceIncreased
                    ? 'The price of this item has increased.'
                    : 'The price of this item has decreased.',
                $item->public_id,
                [
                    'previous_unit_price_minor' => $item->unit_price_at_add_minor,
                    'current_unit_price_minor' => $quote->finalAmountMinor,
                ],
            );
            $issues[] = new CartIssueData(
                CartIssueCode::PriceChanged,
                'The current price is different from when this item was added.',
                $item->public_id,
            );
        }

        if ($hasPrice && $item->discount_at_add_minor > 0 && $quote->discountAmountMinor === 0) {
            $issues[] = new CartIssueData(
                CartIssueCode::PromotionEnded,
                'A promotion on this item is no longer available.',
                $item->public_id,
            );
        } elseif ($hasPrice && $item->discount_at_add_minor === 0 && $quote->discountAmountMinor > 0) {
            $issues[] = new CartIssueData(
                CartIssueCode::PromotionApplied,
                'A promotion now applies to this item.',
                $item->public_id,
            );
        }

        $maxAllowed = $this->availability->maximumAllowed($availability, $hasPrice && $productOk && $variantOk);
        $isAvailable = $hasPrice && $productOk && $variantOk && $availability->availableToSell > 0;

        if ($productOk && $variantOk && $hasPrice && $item->quantity > $maxAllowed) {
            $issues[] = new CartIssueData(
                CartIssueCode::InsufficientStock,
                'Requested quantity is higher than current availability.',
                $item->public_id,
            );
        }

        $translation = $product === null ? null : $product->translation($locale);
        $name = (string) (($translation !== null ? $translation->name : null) ?? $item->product_name_at_add ?? '');
        $label = $this->variantLabel($item, $locale);
        $sku = $variant === null
            ? (string) ($item->sku_at_add ?? '')
            : (string) $variant->sku;

        return new CartLineSnapshotData(
            publicId: $item->public_id,
            productId: (int) $item->product_id,
            variantId: (int) $item->variant_id,
            quantity: (int) $item->quantity,
            sku: $sku,
            productName: (string) $name,
            variantLabel: $label,
            pricing: new CartLinePricingData(
                unitPriceMinor: $unit,
                compareAtPriceMinor: $compare,
                lineSubtotalMinor: $lineSubtotal,
                lineDiscountMinor: $lineDiscount,
                lineTotalMinor: $lineTotal,
                currency: $item->currency,
                priceChanged: $priceChanged,
                pricingSignature: $quote?->signature,
            ),
            availability: new CartLineAvailabilityData(
                isAvailable: $isAvailable,
                canIncrement: $isAvailable && $item->quantity < $maxAllowed,
                canDecrement: $item->quantity > 1,
                maximumAllowedQuantity: $maxAllowed,
                requestedQuantity: (int) $item->quantity,
            ),
            issues: $issues,
        );
    }

    private function variantLabel(CartItem $item, string $locale): string
    {
        $variant = $item->variant;
        if ($variant === null) {
            return (string) ($item->variant_name_at_add ?? '');
        }

        $parts = [];
        foreach ($variant->attributeValues as $value) {
            $label = $value->localizedName($locale);
            if (is_string($label) && $label !== '') {
                $parts[] = $label;
            }
        }

        if ($parts !== []) {
            return implode(' / ', $parts);
        }

        return (string) ($item->variant_name_at_add ?? $variant->sku);
    }
}
