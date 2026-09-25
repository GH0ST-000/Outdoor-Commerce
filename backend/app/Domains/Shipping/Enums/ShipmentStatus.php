<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Enums;

enum ShipmentStatus: string
{
    case Draft = 'draft';
    case Preparing = 'preparing';
    case ReadyForDispatch = 'ready_for_dispatch';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case DeliveryAttemptFailed = 'delivery_attempt_failed';
    case Delivered = 'delivered';
    case ReadyForPickup = 'ready_for_pickup';
    case Collected = 'collected';
    case Exception = 'exception';
    case Cancelled = 'cancelled';

    public function isTerminalSuccess(): bool
    {
        return $this === self::Delivered || $this === self::Collected;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function occupiesAllocation(): bool
    {
        return $this !== self::Cancelled;
    }

    public function hasLeftTheWarehouse(): bool
    {
        return in_array($this, [
            self::Shipped,
            self::InTransit,
            self::OutForDelivery,
            self::DeliveryAttemptFailed,
            self::Delivered,
        ], true);
    }

    public function isPickupStatus(): bool
    {
        return in_array($this, [
            self::ReadyForPickup,
            self::Collected,
        ], true);
    }

    public function isDeliveryStatus(): bool
    {
        return in_array($this, [
            self::ReadyForDispatch,
            self::Shipped,
            self::InTransit,
            self::OutForDelivery,
            self::DeliveryAttemptFailed,
            self::Delivered,
        ], true);
    }

    public function allowsCustomerCancellationOfOrder(): bool
    {
        return in_array($this, [self::Draft, self::Preparing, self::Cancelled], true);
    }
}
