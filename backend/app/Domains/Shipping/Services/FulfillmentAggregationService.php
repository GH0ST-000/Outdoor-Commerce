<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Events\FulfillmentStarted;
use App\Domains\Shipping\Events\OrderFulfilled;
use App\Domains\Shipping\Events\OrderPartiallyFulfilled;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Models\ShipmentItem;
use App\Domains\Shipping\Support\ShipmentLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class FulfillmentAggregationService
{
    public function __construct(private readonly ShipmentLogger $logger) {}

    public function recalculate(Order $order): FulfillmentStatus
    {
        $order->loadMissing('items');
        $shipments = Shipment::query()
            ->where('order_id', $order->id)
            ->with('items')
            ->lockForUpdate()
            ->get();

        $previous = $order->fulfillment_status;
        $next = $this->derive($order, $shipments);

        if ($previous !== $next) {
            $order->fulfillment_status = $next;
            $order->save();
            $this->logger->info('aggregate_status', [
                'order_public_id' => $order->public_id,
                'from' => $previous->value,
                'to' => $next->value,
            ]);
            $this->dispatchAggregateEvents($order, $previous, $next);
        }

        return $next;
    }

    /**
     * @param  Collection<int, Shipment>  $shipments
     */
    public function derive(Order $order, $shipments): FulfillmentStatus
    {
        if ($shipments->isEmpty()) {
            return FulfillmentStatus::Unfulfilled;
        }

        $hasException = $shipments->contains(
            fn (Shipment $shipment): bool => $shipment->status === ShipmentStatus::Exception,
        );
        if ($hasException) {
            return FulfillmentStatus::Exception;
        }

        $active = $shipments->filter(
            fn (Shipment $shipment): bool => $shipment->status->occupiesAllocation(),
        );

        if ($active->isEmpty()) {
            return FulfillmentStatus::Cancelled;
        }

        $ordered = (int) $order->items->sum(fn (OrderItem $item): int => $item->quantity);
        $completed = 0;
        $inProgress = false;

        foreach ($active as $shipment) {
            if (! $shipment->status->isTerminalSuccess()) {
                $inProgress = true;
            }
            foreach ($shipment->items as $item) {
                $completed += $item->delivered_quantity;
            }
        }

        if ($completed >= $ordered && $ordered > 0 && ! $inProgress) {
            return FulfillmentStatus::Fulfilled;
        }

        if ($completed > 0) {
            return FulfillmentStatus::PartiallyFulfilled;
        }

        return FulfillmentStatus::Processing;
    }

    /**
     * Remaining fulfillable quantity per order item id.
     *
     * @return array<int, int>
     */
    public function remainingByOrderItemId(Order $order): array
    {
        $allocated = ShipmentItem::query()
            ->selectRaw('order_item_id, SUM(quantity - cancelled_quantity) as allocated')
            ->whereHas('shipment', function ($query) use ($order): void {
                $query->where('order_id', $order->id)
                    ->where('status', '!=', ShipmentStatus::Cancelled->value);
            })
            ->groupBy('order_item_id')
            ->pluck('allocated', 'order_item_id');

        $remaining = [];
        foreach ($order->items as $item) {
            $used = (int) ($allocated[$item->id] ?? 0);
            $remaining[$item->id] = max(0, $item->quantity - $used);
        }

        return $remaining;
    }

    private function dispatchAggregateEvents(
        Order $order,
        FulfillmentStatus $previous,
        FulfillmentStatus $next,
    ): void {
        $publicId = $order->public_id;
        DB::afterCommit(function () use ($previous, $next, $publicId): void {
            if ($previous === FulfillmentStatus::Unfulfilled && $next->hasStarted()) {
                event(new FulfillmentStarted($publicId));
            }
            if ($next === FulfillmentStatus::PartiallyFulfilled) {
                event(new OrderPartiallyFulfilled($publicId));
            }
            if ($next === FulfillmentStatus::Fulfilled) {
                event(new OrderFulfilled($publicId));
            }
        });
    }
}
