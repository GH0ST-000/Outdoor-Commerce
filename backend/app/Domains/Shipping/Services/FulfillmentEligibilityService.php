<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Shipping\Enums\FulfillmentType;
use App\Domains\Shipping\Exceptions\ShipmentException;

final class FulfillmentEligibilityService
{
    public function assertCanCreateShipment(Order $order): void
    {
        if ($order->payment_status !== PaymentStatus::Paid) {
            throw ShipmentException::orderNotPaid();
        }

        if ($order->status !== OrderStatus::Confirmed) {
            throw ShipmentException::orderNotEligible();
        }

        $committed = InventoryReservation::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $order->public_id)
            ->where('status', InventoryReservationStatus::Committed)
            ->exists();

        if (! $committed) {
            throw ShipmentException::inventoryNotCommitted();
        }
    }

    public function fulfillmentTypeForOrder(Order $order): FulfillmentType
    {
        $methodType = (string) ($order->fulfillment_snapshot['method_type'] ?? '');

        return match ($methodType) {
            'store_pickup' => FulfillmentType::StorePickup,
            'local_delivery', 'courier_delivery' => FulfillmentType::Delivery,
            default => str_contains($order->fulfillment_method_code, 'pickup')
                ? FulfillmentType::StorePickup
                : FulfillmentType::Delivery,
        };
    }

    public function assertTypeCompatible(Order $order, FulfillmentType $type): void
    {
        if ($this->fulfillmentTypeForOrder($order) !== $type) {
            throw ShipmentException::typeInvalid();
        }
    }
}
