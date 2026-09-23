<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderAdjustmentType;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAdjustment;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Shared\Support\Clock;

final class OrderPresenter
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * @return array{data: array<string, mixed>}
     */
    public function present(Order $order): array
    {
        $order->loadMissing(['items', 'adjustments']);
        $canCancel = $this->canCancel($order);

        return [
            'data' => [
                'id' => $order->public_id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'fulfillment_status' => $order->fulfillment_status->value,
                'currency' => $order->currency,
                'placed_at' => $order->placed_at->toIso8601String(),
                'payment_expires_at' => $order->reservation_expires_at?->toIso8601String(),
                'quote_revision' => $order->quote_revision,
                'contact' => $this->contact($order),
                'address' => $this->safeAddress($order->shipping_address_snapshot),
                'items' => $order->items->map(fn (OrderItem $item): array => $this->item($item))->values()->all(),
                'fulfillment' => $this->fulfillment($order),
                'totals' => [
                    'items_subtotal_minor' => $order->items_subtotal_minor,
                    'discount_total_minor' => $order->discount_total_minor,
                    'delivery_total_minor' => $order->delivery_total_minor,
                    'tax_total_minor' => $order->tax_total_minor,
                    'grand_total_minor' => $order->grand_total_minor,
                    'currency' => $order->currency,
                    'price_includes_tax' => $order->price_includes_tax,
                ],
                'adjustments' => $order->adjustments
                    ->filter(fn (OrderAdjustment $adjustment): bool => $adjustment->type !== OrderAdjustmentType::Manual)
                    ->map(fn (OrderAdjustment $adjustment): array => [
                        'type' => $adjustment->type->value,
                        'code' => $adjustment->code,
                        'label' => $adjustment->label,
                        'amount_minor' => $adjustment->amount_minor,
                    ])
                    ->values()
                    ->all(),
                'can_cancel' => $canCancel,
            ],
        ];
    }

    public function canCancel(Order $order): bool
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            return false;
        }
        if (! in_array($order->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Failed], true)) {
            return false;
        }
        if ($order->fulfillment_status !== FulfillmentStatus::Unfulfilled) {
            return false;
        }
        if ($order->reservation_expires_at !== null && $order->reservation_expires_at->lte($this->clock->now())) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(OrderItem $item): array
    {
        $media = $item->media_snapshot;
        $url = is_array($media) && is_string($media['url'] ?? null) ? $media['url'] : null;
        $alt = is_array($media) && is_string($media['alt'] ?? null) ? $media['alt'] : $item->product_name;

        return [
            'id' => $item->public_id,
            'sku' => $item->sku,
            'name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'attributes' => is_array($item->attribute_snapshot) ? $item->attribute_snapshot : [],
            'quantity' => $item->quantity,
            'media' => $url !== null ? ['url' => $url, 'alt' => $alt] : null,
            'pricing' => [
                'unit_base_price_minor' => $item->unit_base_price_minor,
                'unit_effective_price_minor' => $item->unit_effective_price_minor,
                'line_subtotal_minor' => $item->line_subtotal_minor,
                'line_discount_minor' => $item->line_discount_minor,
                'line_total_minor' => $item->line_total_minor,
                'currency' => $item->currency,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fulfillment(Order $order): array
    {
        $snapshot = $order->fulfillment_snapshot;
        $pickup = $snapshot['pickup_location'] ?? null;

        return [
            'method_code' => $order->fulfillment_method_code,
            'method_type' => $snapshot['method_type'] ?? null,
            'name' => $order->fulfillment_method_name,
            'delivery_total_minor' => $order->delivery_total_minor,
            'estimated_min_days' => $snapshot['estimated_min_days'] ?? null,
            'estimated_max_days' => $snapshot['estimated_max_days'] ?? null,
            'pickup_location' => is_array($pickup) ? [
                'id' => $pickup['id'] ?? null,
                'name' => $pickup['name'] ?? null,
                'address' => $pickup['address'] ?? null,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contact(Order $order): array
    {
        $snapshot = $order->contact_snapshot;

        return [
            'first_name' => $order->customer_first_name,
            'last_name' => $order->customer_last_name,
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
            'customer_note' => $order->customer_note,
            'complete' => true,
            'snapshot' => is_array($snapshot) ? [
                'first_name' => $snapshot['first_name'] ?? $order->customer_first_name,
                'last_name' => $snapshot['last_name'] ?? $order->customer_last_name,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $address
     * @return array<string, mixed>|null
     */
    private function safeAddress(?array $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'recipient_first_name' => $address['recipient_first_name'] ?? null,
            'recipient_last_name' => $address['recipient_last_name'] ?? null,
            'country_code' => $address['country_code'] ?? null,
            'region' => $address['region'] ?? null,
            'municipality_or_city' => $address['municipality_or_city'] ?? null,
            'district' => $address['district'] ?? null,
            'street' => $address['street'] ?? null,
            'house_number' => $address['house_number'] ?? null,
            'apartment' => $address['apartment'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
        ];
    }
}
