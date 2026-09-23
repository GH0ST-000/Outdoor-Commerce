<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Inventory\Contracts\CheckoutInventoryService;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderActorType;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\OrderStatusReasonCode;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderExpired;
use App\Domains\Orders\Events\OrderReservationReleased;
use App\Domains\Orders\Exceptions\OrderException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Support\OrderLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;

final class OrderLifecycleService
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly OrderPaymentStatusService $payments,
        private readonly CheckoutInventoryService $inventory,
        private readonly Clock $clock,
        private readonly OrderLogger $logger,
    ) {}

    public function expireIfDue(Order $order, bool $requestTime = false): Order
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return $order;
        }
        if (! in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::PaymentProcessing], true)) {
            return $order;
        }
        if ($order->reservation_expires_at === null || $order->reservation_expires_at->isFuture()) {
            return $order;
        }

        $this->finalize(
            $order,
            OrderStatus::Expired,
            OrderStatusReasonCode::UnpaidExpired,
            OrderActorType::System,
            null,
            'unpaid_expired',
        );

        if ($requestTime) {
            $this->logger->info('request_time_expiration', [
                'order_public_id' => $order->public_id,
            ]);
        }

        return $order;
    }

    public function cancelByCustomer(Order $order, ?int $actorId): Order
    {
        if ($order->status === OrderStatus::Cancelled) {
            return $order;
        }

        if (! $order->status->allowsCustomerCancellation()
            || $order->payment_status->isSuccessful()
            || $order->fulfillment_status->hasStarted()) {
            throw OrderException::cancellationNotAllowed();
        }

        if ($order->reservation_expires_at !== null && $order->reservation_expires_at->lte($this->clock->now())) {
            $this->expireIfDue($order, true);
            throw OrderException::paymentWindowExpired();
        }

        $this->finalize(
            $order,
            OrderStatus::Cancelled,
            OrderStatusReasonCode::CustomerCancelled,
            OrderActorType::Customer,
            $actorId,
            'customer_cancelled',
        );

        return $order;
    }

    private function finalize(
        Order $order,
        OrderStatus $to,
        OrderStatusReasonCode $reason,
        OrderActorType $actorType,
        ?int $actorId,
        string $releaseReason,
    ): void {
        $this->states->transition($order, $to, $reason, $actorType, $actorId);
        if (! $order->payment_status->isSuccessful()) {
            $this->payments->transition($order, PaymentStatus::Cancelled);
        }
        if (! $order->fulfillment_status->hasStarted()) {
            $order->fulfillment_status = FulfillmentStatus::Cancelled;
            $order->save();
        }

        $released = $this->releaseReservations($order, $releaseReason);

        $publicId = $order->public_id;
        $number = $order->order_number;
        DB::afterCommit(function () use ($to, $publicId, $number, $reason, $released, $releaseReason): void {
            if ($to === OrderStatus::Cancelled) {
                event(new OrderCancelled($publicId, $number, $reason->value));
            }
            if ($to === OrderStatus::Expired) {
                event(new OrderExpired($publicId, $number));
            }
            event(new OrderReservationReleased($publicId, $releaseReason, $released));
        });

        $this->logger->info($to === OrderStatus::Expired ? 'expiration' : 'cancellation', [
            'order_public_id' => $publicId,
            'reason_code' => $reason->value,
            'reservations_released' => $released,
        ]);
    }

    public function releaseReservations(Order $order, string $reason): int
    {
        $reservations = InventoryReservation::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $order->public_id)
            ->where('status', InventoryReservationStatus::Active)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $count = 0;
        foreach ($reservations as $reservation) {
            if ($reservation->status === InventoryReservationStatus::Committed) {
                continue;
            }
            $this->inventory->release(
                $reservation,
                'order-release:'.$order->public_id.':'.$reservation->reservation_key.':'.$reason,
                $reason,
            );
            $count++;
        }

        if ($count > 0) {
            $this->logger->info('reservation_release', [
                'order_public_id' => $order->public_id,
                'reason' => $reason,
                'count' => $count,
            ]);
        }

        return $count;
    }
}
