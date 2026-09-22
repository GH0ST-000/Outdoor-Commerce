<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Support;

use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutQuoteLine;
use App\Domains\Checkout\Models\CheckoutSession;

final class QuoteFingerprint
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function hash(array $payload): string
    {
        $encoded = json_encode(self::canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', (string) $encoded, (string) config('checkout.fingerprint_secret'));
    }

    public static function publicValue(string $internalHash): string
    {
        return substr($internalHash, 0, 32);
    }

    /**
     * Commercial fields stored on the quote. Used at create and consume so Day 19
     * can recompute HMAC without re-pricing or replaying catalog signatures.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $addressFingerprint
     * @return array<string, mixed>
     */
    public static function commercialPayload(
        int $cartVersion,
        ?string $email,
        ?string $phone,
        ?string $fulfillmentCode,
        ?string $pickupPublicId,
        array $addressFingerprint,
        string $currency,
        array $lines,
        int $itemsSubtotalMinor,
        int $discountTotalMinor,
        int $deliveryTotalMinor,
        int $taxTotalMinor,
        int $grandTotalMinor,
    ): array {
        $normalized = [];
        foreach ($lines as $line) {
            $normalized[] = [
                'variant_id' => (int) ($line['variant_id'] ?? 0),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_base_price_minor' => (int) ($line['unit_base_price_minor'] ?? 0),
                'unit_effective_price_minor' => (int) ($line['unit_effective_price_minor'] ?? 0),
                'line_subtotal_minor' => (int) ($line['line_subtotal_minor'] ?? 0),
                'line_discount_minor' => (int) ($line['line_discount_minor'] ?? 0),
                'line_total_minor' => (int) ($line['line_total_minor'] ?? 0),
                'sku' => (string) ($line['sku'] ?? ''),
            ];
        }
        usort($normalized, static fn (array $left, array $right): int => $left['variant_id'] <=> $right['variant_id']);

        return [
            'cart_version' => $cartVersion,
            'session_inputs' => [
                'email' => $email,
                'phone' => $phone,
                'fulfillment' => $fulfillmentCode,
                'pickup' => $pickupPublicId,
                'address' => $addressFingerprint,
            ],
            'currency' => $currency,
            'lines' => $normalized,
            'items_subtotal_minor' => $itemsSubtotalMinor,
            'discount_total_minor' => $discountTotalMinor,
            'delivery_total_minor' => $deliveryTotalMinor,
            'tax_total_minor' => $taxTotalMinor,
            'grand_total_minor' => $grandTotalMinor,
        ];
    }

    public static function fromStoredQuote(CheckoutQuote $quote, CheckoutSession $session): string
    {
        $quote->loadMissing('lines');
        $session->loadMissing(['fulfillmentMethod', 'pickupLocation', 'shippingAddress']);

        $lines = $quote->lines->map(static fn (CheckoutQuoteLine $line): array => [
            'variant_id' => $line->variant_id,
            'quantity' => $line->quantity,
            'unit_base_price_minor' => $line->unit_base_price_minor,
            'unit_effective_price_minor' => $line->unit_effective_price_minor,
            'line_subtotal_minor' => $line->line_subtotal_minor,
            'line_discount_minor' => $line->line_discount_minor,
            'line_total_minor' => $line->line_total_minor,
            'sku' => $line->sku,
        ])->values()->all();

        return self::hash(self::commercialPayload(
            $quote->cart_version,
            $session->email,
            $session->phone,
            $session->fulfillmentMethod?->code,
            $session->pickupLocation?->public_id,
            $session->shippingAddress?->fingerprintPayload() ?? [],
            $quote->currency,
            $lines,
            $quote->items_subtotal_minor,
            $quote->discount_total_minor,
            $quote->delivery_total_minor,
            $quote->tax_total_minor,
            $quote->grand_total_minor,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function canonicalize(array $payload): array
    {
        ksort($payload);
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $isList = array_is_list($value);
                $payload[$key] = $isList
                    ? array_map(
                        static fn (mixed $item): mixed => is_array($item) ? self::canonicalize($item) : $item,
                        $value,
                    )
                    : self::canonicalize($value);
            }
        }

        return $payload;
    }
}
