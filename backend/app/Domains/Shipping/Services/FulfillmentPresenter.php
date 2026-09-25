<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Orders\Models\Order;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Models\ShipmentEvent;
use App\Domains\Shipping\Models\ShipmentItem;
use Illuminate\Support\Collection;

final class FulfillmentPresenter
{
    public function __construct(
        private readonly FulfillmentAggregationService $aggregation,
    ) {}

    /**
     * @return array{data: array<string, mixed>}
     */
    public function presentCustomer(Order $order, string $locale): array
    {
        $order->loadMissing('items');
        $shipments = Shipment::query()
            ->where('order_id', $order->id)
            ->with(['items.orderItem', 'events'])
            ->orderBy('id')
            ->get();

        return [
            'data' => [
                'order_id' => $order->public_id,
                'fulfillment_status' => $order->fulfillment_status->value,
                'shipments' => $shipments->map(fn (Shipment $shipment): array => $this->customerShipment($shipment, $locale))->values()->all(),
                'remaining_items' => $this->remainingItems($order),
                'capabilities' => [
                    'can_refresh' => true,
                    'poll' => $this->shouldPoll($shipments),
                ],
            ],
        ];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function presentAdminOrder(Order $order, string $locale): array
    {
        $customer = $this->presentCustomer($order, $locale);
        $shipments = Shipment::query()
            ->where('order_id', $order->id)
            ->with(['items.orderItem', 'events', 'warehouse'])
            ->orderBy('id')
            ->get();

        $customer['data']['shipments'] = $shipments
            ->map(fn (Shipment $shipment): array => $this->adminShipment($shipment, $locale))
            ->values()
            ->all();

        return $customer;
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function presentAdminShipment(Shipment $shipment, string $locale): array
    {
        $shipment->loadMissing(['items.orderItem', 'events', 'warehouse', 'order']);

        return ['data' => $this->adminShipment($shipment, $locale)];
    }

    /**
     * @param  array{data: array<string, mixed>}  $orderPayload
     * @return array{data: array<string, mixed>}
     */
    public function enrichOrder(array $orderPayload, Order $order, string $locale): array
    {
        $block = $this->presentCustomer($order, $locale)['data'];
        $orderPayload['data']['fulfillment_progress'] = $block;

        return $orderPayload;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerShipment(Shipment $shipment, string $locale): array
    {
        return [
            'id' => $shipment->public_id,
            'shipment_number' => $shipment->shipment_number,
            'type' => $shipment->fulfillment_type->value,
            'status' => $shipment->status->value,
            'provider' => [
                'code' => $shipment->provider_code,
                'name' => $shipment->carrier_display_name ?? ($shipment->provider_code === 'manual' ? $this->manualLabel($locale) : $shipment->provider_code),
                'manual' => $shipment->provider_code === 'manual',
            ],
            'tracking' => [
                'number' => $shipment->tracking_number,
                'url' => $shipment->public_tracking_url,
            ],
            'items' => $shipment->items->map(fn (ShipmentItem $item): array => $this->item($item))->values()->all(),
            'timeline' => $shipment->events->map(fn (ShipmentEvent $event): array => $this->customerEvent($event, $locale))->values()->all(),
            'pickup_location' => $this->safePickup($shipment),
            'estimated_delivery' => [
                'from' => $shipment->estimated_delivery_from?->toIso8601String(),
                'to' => $shipment->estimated_delivery_to?->toIso8601String(),
                'is_guaranteed' => $shipment->estimated_delivery_is_guaranteed,
            ],
            'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            'collected_at' => $shipment->collected_at?->toIso8601String(),
            'exception' => $shipment->status === ShipmentStatus::Exception ? [
                'code' => $shipment->exception_code?->value,
                'message' => $this->exceptionMessage($shipment->exception_code?->value, $locale),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminShipment(Shipment $shipment, string $locale): array
    {
        $payload = $this->customerShipment($shipment, $locale);
        $payload['version'] = $shipment->version;
        $payload['warehouse_code'] = $shipment->warehouse?->code;
        $payload['internal_timeline'] = $shipment->events->map(fn (ShipmentEvent $event): array => [
            'id' => $event->public_id,
            'status' => $event->status->value,
            'event_code' => $event->event_code->value,
            'source' => $event->source->value,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'internal_message' => $event->internal_message,
        ])->values()->all();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(ShipmentItem $item): array
    {
        $orderItem = $item->orderItem;
        $media = is_array($orderItem?->media_snapshot) ? $orderItem->media_snapshot : null;

        return [
            'order_item_id' => $orderItem?->public_id,
            'name' => $orderItem?->product_name,
            'variant_name' => $orderItem?->variant_name,
            'quantity' => $item->quantity,
            'media' => is_array($media) && is_string($media['url'] ?? null) ? [
                'url' => $media['url'],
                'alt' => $media['alt'] ?? $orderItem?->product_name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerEvent(ShipmentEvent $event, string $locale): array
    {
        return [
            'status' => $event->status->value,
            'message' => $this->customerMessage($event->customer_message_key ?? $event->status->value, $locale),
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'location' => $event->location_label,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function remainingItems(Order $order): array
    {
        $remaining = $this->aggregation->remainingByOrderItemId($order);
        $rows = [];
        foreach ($order->items as $item) {
            $left = $remaining[$item->id] ?? 0;
            if ($left < 1) {
                continue;
            }
            $rows[] = [
                'order_item_id' => $item->public_id,
                'name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity' => $left,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Shipment>  $shipments
     */
    private function shouldPoll($shipments): bool
    {
        foreach ($shipments as $shipment) {
            if (! $shipment->status->isTerminalSuccess() && ! $shipment->status->isCancelled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safePickup(Shipment $shipment): ?array
    {
        $pickup = $shipment->pickup_location_snapshot;
        if (! is_array($pickup)) {
            return null;
        }

        return [
            'id' => $pickup['id'] ?? $shipment->pickup_location_public_id,
            'name' => $pickup['name'] ?? null,
            'address' => $pickup['address'] ?? null,
            'instructions' => $pickup['instructions'] ?? null,
            'working_hours' => $pickup['working_hours'] ?? null,
        ];
    }

    private function manualLabel(string $locale): string
    {
        return $locale === 'ka' ? 'ხელით მიწოდება' : 'Manual fulfillment';
    }

    private function customerMessage(string $key, string $locale): string
    {
        $ka = [
            'created' => 'გზავნილი შეიქმნა',
            'preparation_started' => 'მზადება დაიწყო',
            'picked' => 'პროდუქტები შეირჩა',
            'packed' => 'პროდუქტები შეფუთულია',
            'ready_for_dispatch' => 'მზად არის გასაგზავნად',
            'dispatched' => 'გზავნილი გაიგზავნა',
            'in_transit' => 'გზაშია',
            'out_for_delivery' => 'მიწოდების პროცესშია',
            'delivery_attempt_failed' => 'მიწოდების მცდელობა ვერ შესრულდა',
            'delivered' => 'მიწოდებულია',
            'ready_for_pickup' => 'მზად არის გასატანად',
            'collected' => 'გატანილია',
            'exception' => 'საჭიროა დამატებითი შემოწმება',
            'cancelled' => 'გზავნილი გაუქმდა',
        ];
        $en = [
            'created' => 'Shipment created',
            'preparation_started' => 'Preparation started',
            'picked' => 'Items picked',
            'packed' => 'Items packed',
            'ready_for_dispatch' => 'Ready for dispatch',
            'dispatched' => 'Shipped',
            'in_transit' => 'In transit',
            'out_for_delivery' => 'Out for delivery',
            'delivery_attempt_failed' => 'Delivery attempt did not complete',
            'delivered' => 'Delivered',
            'ready_for_pickup' => 'Ready for pickup',
            'collected' => 'Collected',
            'exception' => 'This shipment needs attention',
            'cancelled' => 'Shipment cancelled',
        ];

        $map = $locale === 'ka' ? $ka : $en;

        return $map[$key] ?? ($locale === 'ka' ? 'სტატუსი განახლდა' : 'Status updated');
    }

    private function exceptionMessage(?string $code, string $locale): string
    {
        $ka = [
            'address_issue' => 'მისამართთან დაკავშირებული საკითხი',
            'recipient_unavailable' => 'მიმღები ვერ მოიძებნა',
            'delivery_attempt_failed' => 'მიწოდების მცდელობა ვერ შესრულდა',
            'carrier_delay' => 'გადამზიდავის დაგვიანება',
            'damaged_package' => 'შეფუთვა დაზიანდა',
            'lost_package' => 'გზავნილი დაიკარგა',
            'weather_delay' => 'ამინდის გამო დაგვიანება',
            'provider_error' => 'მიწოდების სერვისის შეფერხება',
            'unknown' => 'საჭიროა დამატებითი შემოწმება',
        ];
        $en = [
            'address_issue' => 'There is an address issue with this delivery.',
            'recipient_unavailable' => 'The recipient was not available.',
            'delivery_attempt_failed' => 'A delivery attempt did not complete.',
            'carrier_delay' => 'The carrier reported a delay.',
            'damaged_package' => 'The package was reported damaged.',
            'lost_package' => 'The package was reported lost.',
            'weather_delay' => 'Delivery is delayed by weather.',
            'provider_error' => 'The carrier reported a problem.',
            'unknown' => 'This shipment needs a short operational review.',
        ];

        $map = $locale === 'ka' ? $ka : $en;

        return $map[$code ?? 'unknown'] ?? $map['unknown'];
    }
}
