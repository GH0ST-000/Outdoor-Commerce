<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Services;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\DTOs\CheckoutActorData;
use App\Domains\Checkout\Enums\CheckoutQuoteAdjustmentType;
use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Checkout\Events\CheckoutQuoteCreated;
use App\Domains\Checkout\Events\CheckoutQuoteSuperseded;
use App\Domains\Checkout\Events\InventoryReservedForQuote;
use App\Domains\Checkout\Exceptions\CheckoutException;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutQuoteAdjustment;
use App\Domains\Checkout\Models\CheckoutQuoteLine;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Checkout\Support\CheckoutLogger;
use App\Domains\Checkout\Support\QuoteFingerprint;
use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Inventory\DTOs\PublicAvailabilityData;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Pricing\DTOs\PublicPriceQuoteData;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CheckoutQuoteService
{
    public function __construct(
        private readonly PublicCatalogPricing $pricing,
        private readonly PublicInventoryAvailability $availability,
        private readonly CheckoutFulfillmentService $fulfillment,
        private readonly CheckoutRestrictionService $restrictions,
        private readonly CheckoutReservationService $reservations,
        private readonly Clock $clock,
        private readonly CheckoutLogger $logger,
    ) {}

    public function create(CheckoutSession $session, Cart $cart, CheckoutActorData $actor): CheckoutQuote
    {
        $started = microtime(true);
        $maxRefreshes = max(1, (int) config('checkout.max_quote_refreshes_per_session', 8));
        if ($session->quote_refresh_count >= $maxRefreshes) {
            throw CheckoutException::refreshLimit();
        }

        $this->assertReadyForQuote($session);
        $this->assertCurrency($session, $cart, $actor);

        $cart->loadMissing([
            'items.product.translations',
            'items.product.brand.translations',
            'items.variant.attributeValues.translations',
            'items.variant.attributeValues.attribute.translations',
        ]);

        /** @var list<CartItem> $items */
        $items = $cart->items->sortBy('variant_id')->values()->all();
        if ($items === []) {
            throw CheckoutException::cartEmpty();
        }

        $variantIds = array_map(static fn (CartItem $item): int => (int) $item->variant_id, $items);
        $productIds = array_map(static fn (CartItem $item): int => (int) $item->product_id, $items);
        $quotes = $this->pricing->quoteVariants($variantIds, $actor->priceListId());
        $stock = $this->availability->forVariants($variantIds);
        $restrictionRules = $this->restrictions->rulesFor($productIds);
        $maxQty = max(1, (int) config('checkout.max_line_quantity', 12));

        $linePlans = [];
        $itemsSubtotal = 0;
        $discountTotal = 0;
        $adjustments = [];

        foreach ($items as $item) {
            $linePlans[] = $this->planLine(
                $item,
                $quotes[$item->variant_id] ?? null,
                $stock[$item->variant_id] ?? null,

                $restrictionRules[$item->product_id] ?? null,
                $actor->locale(),
                $maxQty,
            );
            $plan = $linePlans[array_key_last($linePlans)];
            $itemsSubtotal += $plan['line_subtotal_minor'];
            $discountTotal += $plan['line_discount_minor'];
        }

        $fulfillmentQuote = $this->fulfillment->quoteSelected($session, $itemsSubtotal);
        $deliveryTotal = max(0, (int) $fulfillmentQuote->amountMinor);
        if ($deliveryTotal > 0) {
            $adjustments[] = [
                'type' => CheckoutQuoteAdjustmentType::Delivery,
                'code' => $session->fulfillmentMethod->code,
                'label' => $session->fulfillmentMethod->localizedName($actor->locale()),
                'amount_minor' => $deliveryTotal,
                'quote_line' => null,
                'metadata' => [
                    'estimated_min_days' => $fulfillmentQuote->estimatedMinDays,
                    'estimated_max_days' => $fulfillmentQuote->estimatedMaxDays,
                ],
            ];
        } elseif (($fulfillmentQuote->metadata['free_applied'] ?? false) === true) {
            $adjustments[] = [
                'type' => CheckoutQuoteAdjustmentType::Delivery,
                'code' => 'free_delivery',
                'label' => $session->fulfillmentMethod->localizedName($actor->locale()),
                'amount_minor' => 0,
                'quote_line' => null,
                'metadata' => ['free_applied' => true],
            ];
        }

        $taxTotal = 0;
        $grandTotal = max(0, $itemsSubtotal - $discountTotal + $deliveryTotal + $taxTotal);
        $priceIncludesTax = (bool) config('checkout.price_includes_tax', true);
        $expiresAt = $this->clock->now()->addMinutes(max(1, (int) config('checkout.quote_ttl_minutes', 15)));
        $revision = (int) CheckoutQuote::query()->where('checkout_session_id', $session->id)->max('revision') + 1;
        $publicId = (string) Str::uuid();

        $fingerprint = QuoteFingerprint::hash(QuoteFingerprint::commercialPayload(
            $cart->version,
            $session->email,
            $session->phone,
            $session->fulfillmentMethod?->code,
            $session->pickupLocation?->public_id,
            $session->shippingAddress?->fingerprintPayload() ?? [],
            $session->currency,
            array_map(static fn (array $plan): array => [
                'variant_id' => $plan['variant_id'],
                'quantity' => $plan['quantity'],
                'unit_base_price_minor' => $plan['unit_base_price_minor'],
                'unit_effective_price_minor' => $plan['unit_effective_price_minor'],
                'line_subtotal_minor' => $plan['line_subtotal_minor'],
                'line_discount_minor' => $plan['line_discount_minor'],
                'line_total_minor' => $plan['line_total_minor'],
                'sku' => $plan['sku'],
            ], $linePlans),
            $itemsSubtotal,
            $discountTotal,
            $deliveryTotal,
            $taxTotal,
            $grandTotal,
        ));

        $previous = $session->currentQuote;

        if ($previous !== null && $previous->status === CheckoutQuoteStatus::Active) {
            $this->reservations->releaseQuote($previous, 'superseded');
        }

        $quote = CheckoutQuote::query()->create([
            'public_id' => $publicId,
            'checkout_session_id' => $session->id,
            'revision' => $revision,
            'status' => CheckoutQuoteStatus::Active,
            'cart_version' => $cart->version,
            'currency' => $session->currency,
            'items_subtotal_minor' => $itemsSubtotal,
            'discount_total_minor' => $discountTotal,
            'delivery_total_minor' => $deliveryTotal,
            'tax_total_minor' => $taxTotal,
            'grand_total_minor' => $grandTotal,
            'price_includes_tax' => $priceIncludesTax,
            'quote_fingerprint' => $fingerprint,
            'fulfillment_snapshot' => [
                'method_code' => $session->fulfillmentMethod?->code,
                'method_type' => $session->fulfillmentMethod !== null ? $session->fulfillmentMethod->type->value : null,
                'name' => $session->fulfillmentMethod?->localizedName($actor->locale()),
                'amount_minor' => $deliveryTotal,
                'estimated_min_days' => $fulfillmentQuote->estimatedMinDays,
                'estimated_max_days' => $fulfillmentQuote->estimatedMaxDays,
                'pickup_location_id' => $session->pickupLocation?->public_id,
                'pickup_location' => $session->pickupLocation === null ? null : [
                    'id' => $session->pickupLocation->public_id,
                    'code' => $session->pickupLocation->code,
                    'name' => $session->pickupLocation->localizedName($actor->locale()),
                    'address' => $session->pickupLocation->localizedAddress($actor->locale()),
                ],
            ],
            'expires_at' => $expiresAt,
        ]);

        $reserved = $this->reservations->reserveLines($quote, array_map(
            static fn (array $plan): array => [
                'variant_id' => $plan['variant_id'],
                'quantity' => $plan['quantity'],
                'item_public_id' => $plan['cart_item_public_id'],
            ],
            $linePlans,
        ), $expiresAt);

        foreach ($linePlans as $plan) {
            $reservation = $reserved[$plan['variant_id']] ?? null;
            $line = CheckoutQuoteLine::query()->create([
                'checkout_quote_id' => $quote->id,
                'public_id' => (string) Str::uuid(),
                'product_id' => $plan['product_id'],
                'variant_id' => $plan['variant_id'],
                'sku' => $plan['sku'],
                'product_name' => $plan['product_name'],
                'variant_label' => $plan['variant_label'],
                'slug' => $plan['slug'],
                'quantity' => $plan['quantity'],
                'unit_base_price_minor' => $plan['unit_base_price_minor'],
                'unit_effective_price_minor' => $plan['unit_effective_price_minor'],
                'unit_discount_minor' => $plan['unit_discount_minor'],
                'line_subtotal_minor' => $plan['line_subtotal_minor'],
                'line_discount_minor' => $plan['line_discount_minor'],
                'line_total_minor' => $plan['line_total_minor'],
                'currency' => $session->currency,
                'promotion_snapshot' => $plan['promotion_snapshot'],
                'attribute_snapshot' => $plan['attribute_snapshot'],
                'media_snapshot' => $plan['media_snapshot'],
                'restriction_snapshot' => $plan['restriction_snapshot'],
                'reservation_key' => $reservation?->reservation_key,
                'created_at' => $this->clock->now(),
            ]);

            foreach ($plan['adjustments'] as $adjustment) {
                CheckoutQuoteAdjustment::query()->create([
                    'checkout_quote_id' => $quote->id,
                    'quote_line_id' => $line->id,
                    'type' => $adjustment['type'],
                    'code' => $adjustment['code'],
                    'label' => $adjustment['label'],
                    'amount_minor' => $adjustment['amount_minor'],
                    'metadata' => $adjustment['metadata'] ?? null,
                    'created_at' => $this->clock->now(),
                ]);
            }
        }

        foreach ($adjustments as $adjustment) {
            CheckoutQuoteAdjustment::query()->create([
                'checkout_quote_id' => $quote->id,
                'quote_line_id' => null,
                'type' => $adjustment['type'],
                'code' => $adjustment['code'],
                'label' => $adjustment['label'],
                'amount_minor' => $adjustment['amount_minor'],
                'metadata' => $adjustment['metadata'],
                'created_at' => $this->clock->now(),
            ]);
        }

        if ($previous !== null && $previous->status === CheckoutQuoteStatus::Active) {
            $previousPublicId = $previous->public_id;
            $previousRevision = $previous->revision;
            $previous->status = CheckoutQuoteStatus::Superseded;
            $previous->superseded_at = $this->clock->now();
            $previous->save();

            DB::afterCommit(function () use ($session, $previousPublicId, $previousRevision): void {
                event(new CheckoutQuoteSuperseded($session->public_id, $previousPublicId, $previousRevision));
            });
        }

        $session->current_quote_id = $quote->id;
        $session->status = CheckoutSessionStatus::Quoted;
        $session->quote_refresh_count = $session->quote_refresh_count + 1;
        $session->version = $session->version + 1;
        $session->last_activity_at = $this->clock->now();
        $session->save();

        $latency = (int) round((microtime(true) - $started) * 1000);
        $this->logger->info('quote_created', [
            'session_public_id' => $session->public_id,
            'quote_public_id' => $quote->public_id,
            'revision' => $revision,
            'latency_ms' => $latency,
        ]);

        DB::afterCommit(function () use ($session, $quote, $revision, $grandTotal): void {
            event(new CheckoutQuoteCreated($session->public_id, $quote->public_id, $revision, $grandTotal));
            event(new InventoryReservedForQuote($quote->public_id, $quote->lines()->count()));
        });

        return $quote->fresh(['lines', 'adjustments']) ?? $quote;
    }

    private function assertReadyForQuote(CheckoutSession $session): void
    {
        if (! $session->hasContact()) {
            throw CheckoutException::contactIncomplete();
        }

        $method = $session->fulfillmentMethod;
        if ($method === null) {
            throw CheckoutException::fulfillmentRequired();
        }

        if ($method->requires_address && $session->shippingAddress === null) {
            throw CheckoutException::addressIncomplete();
        }
    }

    private function assertCurrency(CheckoutSession $session, Cart $cart, CheckoutActorData $actor): void
    {
        $expected = strtoupper((string) config('checkout.currency', 'GEL'));
        if (strtoupper($session->currency) !== $expected || strtoupper($cart->currency) !== $expected) {
            throw CheckoutException::currencyMismatch();
        }

        if (strtoupper($actor->currency()) !== $expected) {
            throw CheckoutException::currencyMismatch();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function planLine(
        CartItem $item,
        ?PublicPriceQuoteData $quote,
        ?PublicAvailabilityData $availability,
        mixed $restrictionRule,
        string $locale,
        int $maxQty,
    ): array {
        $availableToSell = $availability === null ? 0 : $availability->availableToSell;
        $product = $item->product;
        $variant = $item->variant;
        $productOk = $product instanceof Product
            && $product->status === ProductStatus::Active
            && $product->published_at !== null;
        $variantOk = $variant instanceof ProductVariant && $variant->status === ProductVariantStatus::Active;

        if (! $productOk) {
            throw CheckoutException::productUnavailable($item->public_id);
        }
        if (! $variantOk) {
            throw CheckoutException::variantUnavailable($item->public_id);
        }
        if ($quote === null) {
            throw CheckoutException::productUnavailable($item->public_id);
        }
        if (strtoupper($quote->currencyCode) !== strtoupper($item->currency)) {
            throw CheckoutException::currencyMismatch();
        }
        if ($item->quantity > $maxQty) {
            throw CheckoutException::quantityLimitExceeded($item->public_id);
        }
        if ($item->quantity > $availableToSell) {
            throw CheckoutException::insufficientStock([
                'item_id' => $item->public_id,
                'available_to_sell' => max(0, $availableToSell),
            ]);
        }

        $this->restrictions->assertNotBlocked($restrictionRule, $locale, $item->public_id);

        $translation = $product->translation($locale);
        $name = (string) (($translation !== null ? $translation->name : null) ?? $item->product_name_at_add ?? '');
        $slug = $translation !== null ? $translation->slug : null;
        $label = $this->variantLabel($item, $locale);

        $lineSubtotal = $quote->baseAmountMinor * $item->quantity;
        $lineTotal = $quote->finalAmountMinor * $item->quantity;
        $lineDiscount = max(0, $lineSubtotal - $lineTotal);
        $unitDiscount = max(0, $quote->baseAmountMinor - $quote->finalAmountMinor);

        $promotionSnapshot = [];
        $lineAdjustments = [];
        foreach ($quote->appliedPromotions as $promotion) {
            $promotionSnapshot[] = [
                'code' => $promotion['code'],
                'name' => $promotion['name'],
                'discount_type' => $promotion['discount_type'],
            ];
        }
        if ($lineDiscount > 0 && $promotionSnapshot !== []) {
            $first = $promotionSnapshot[0];
            $lineAdjustments[] = [
                'type' => CheckoutQuoteAdjustmentType::Promotion,
                'code' => $first['code'],
                'label' => $first['name'],
                'amount_minor' => -1 * $lineDiscount,
                'metadata' => ['discount_type' => $first['discount_type']],
            ];
        }

        return [
            'cart_item_public_id' => $item->public_id,
            'product_id' => (int) $item->product_id,
            'variant_id' => (int) $item->variant_id,
            'sku' => (string) ($variant->sku ?? $item->sku_at_add),
            'product_name' => $name,
            'variant_label' => $label,
            'slug' => $slug,
            'quantity' => (int) $item->quantity,
            'unit_base_price_minor' => $quote->baseAmountMinor,
            'unit_effective_price_minor' => $quote->finalAmountMinor,
            'unit_discount_minor' => $unitDiscount,
            'line_subtotal_minor' => $lineSubtotal,
            'line_discount_minor' => $lineDiscount,
            'line_total_minor' => $lineTotal,
            'pricing_signature' => $quote->signature,
            'promotion_snapshot' => $promotionSnapshot === [] ? null : $promotionSnapshot,
            'attribute_snapshot' => $this->attributes($variant, $locale),
            'media_snapshot' => null,
            'restriction_snapshot' => $this->restrictions->snapshot($restrictionRule, $locale),
            'adjustments' => $lineDiscount > 0 ? $lineAdjustments : [],
        ];
    }

    /**
     * @return list<array{code: string, name: string, value: array{code: string, name: string}}>
     */
    private function attributes(ProductVariant $variant, string $locale): array
    {
        $attributes = [];
        foreach ($variant->attributeValues as $value) {
            $attribute = $value->attribute;
            $attributes[] = [
                'code' => (string) $attribute?->code,
                'name' => (string) ($attribute?->localizedName($locale) ?? $attribute?->code),
                'value' => [
                    'code' => (string) $value->code,
                    'name' => (string) ($value->localizedName($locale) ?? $value->code),
                ],
            ];
        }

        return $attributes;
    }

    private function variantLabel(CartItem $item, string $locale): string
    {
        $variant = $item->variant;
        if (! $variant instanceof ProductVariant) {
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
