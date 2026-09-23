<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Services;

use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutQuoteAdjustment;
use App\Domains\Checkout\Models\CheckoutQuoteLine;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Checkout\Support\QuoteFingerprint;
use App\Domains\Shared\Support\Clock;

final class CheckoutPresenter
{
    public function __construct(
        private readonly Clock $clock,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $methods
     * @return array<string, mixed>
     */
    public function session(CheckoutSession $session, array $methods, string $locale, int $cartVersion): array
    {
        $quote = $session->currentQuote;
        $quotePayload = null;
        if ($quote instanceof CheckoutQuote && $quote->status === CheckoutQuoteStatus::Active) {
            $quotePayload = $this->quote($quote, $session);
        }

        return [
            'id' => $session->public_id,
            'status' => $session->status->value,
            'version' => $session->version,
            'currency' => $session->currency,
            'cart_version' => $cartVersion,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'contact' => [
                'first_name' => $session->first_name,
                'last_name' => $session->last_name,
                'email' => $session->email,
                'phone' => $session->phone,
                'customer_note' => $session->customer_note,
                'complete' => $session->hasContact(),
            ],
            'address' => $session->shippingAddress?->publicSnapshot(),
            'billing_same_as_shipping' => $session->billing_same_as_shipping,
            'fulfillment' => [
                'method_code' => $session->fulfillmentMethod?->code,
                'pickup_location_id' => $session->pickupLocation?->public_id,
            ],
            'available_fulfillment_methods' => $methods,
            'quote' => $quotePayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(CheckoutQuote $quote, CheckoutSession $session): array
    {
        $now = $this->clock->now();
        $remaining = max(0, $quote->expires_at->getTimestamp() - $now->getTimestamp());
        $fulfillment = is_array($quote->fulfillment_snapshot) ? $quote->fulfillment_snapshot : [];

        $restrictions = [];
        $items = $quote->lines->map(function (CheckoutQuoteLine $line) use (&$restrictions): array {
            if (is_array($line->restriction_snapshot)) {
                $restrictions[] = $line->restriction_snapshot;
            }

            return [
                'id' => $line->public_id,
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'slug' => $line->slug,
                'name' => $line->product_name,
                'variant_label' => $line->variant_label,
                'sku' => $line->sku,
                'quantity' => $line->quantity,
                'media' => $line->media_snapshot,
                'pricing' => [
                    'unit_base_price_minor' => $line->unit_base_price_minor,
                    'unit_effective_price_minor' => $line->unit_effective_price_minor,
                    'line_subtotal_minor' => $line->line_subtotal_minor,
                    'line_discount_minor' => $line->line_discount_minor,
                    'line_total_minor' => $line->line_total_minor,
                    'currency' => $line->currency,
                ],
                'attributes' => $line->attribute_snapshot ?? [],
                'promotions' => $line->promotion_snapshot ?? [],
                'restriction' => $line->restriction_snapshot,
                'issues' => [],
            ];
        })->all();

        return [
            'id' => $quote->public_id,
            'revision' => $quote->revision,
            'status' => $quote->status->value,
            'currency' => $quote->currency,
            'expires_at' => $quote->expires_at->toIso8601String(),
            'remaining_seconds' => $remaining,
            'cart_version' => $quote->cart_version,
            'items' => $items,
            'fulfillment' => [
                'method_code' => $fulfillment['method_code'] ?? null,
                'name' => $fulfillment['name'] ?? null,
                'amount_minor' => $fulfillment['amount_minor'] ?? $quote->delivery_total_minor,
                'estimated_min_days' => $fulfillment['estimated_min_days'] ?? null,
                'estimated_max_days' => $fulfillment['estimated_max_days'] ?? null,
            ],
            'totals' => [
                'items_subtotal_minor' => $quote->items_subtotal_minor,
                'discount_total_minor' => $quote->discount_total_minor,
                'delivery_total_minor' => $quote->delivery_total_minor,
                'tax_total_minor' => $quote->tax_total_minor,
                'grand_total_minor' => $quote->grand_total_minor,
                'currency' => $quote->currency,
                'price_includes_tax' => $quote->price_includes_tax,
            ],
            'adjustments' => $quote->adjustments->map(static fn (CheckoutQuoteAdjustment $adjustment): array => [
                'type' => $adjustment->type->value,
                'code' => $adjustment->code,
                'label' => $adjustment->label,
                'amount_minor' => $adjustment->amount_minor,
            ])->all(),
            'restrictions' => $restrictions,
            'fingerprint' => QuoteFingerprint::publicValue($quote->quote_fingerprint),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $methods
     * @return array<string, mixed>
     */
    public function envelope(CheckoutSession $session, array $methods, string $locale, int $cartVersion): array
    {
        $session->loadMissing([
            'fulfillmentMethod',
            'pickupLocation',
            'shippingAddress',
            'currentQuote.lines',
            'currentQuote.adjustments',
        ]);

        return [
            'data' => [
                'checkout_session' => $this->session($session, $methods, $locale, $cartVersion),
                'quote' => $session->currentQuote instanceof CheckoutQuote
                    && $session->currentQuote->status === CheckoutQuoteStatus::Active
                    ? $this->quote($session->currentQuote, $session)
                    : null,
            ],
        ];
    }
}
