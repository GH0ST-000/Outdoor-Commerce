<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Enums;

enum ShipmentEventCode: string
{
    case Created = 'created';
    case PreparationStarted = 'preparation_started';
    case ItemsPicked = 'items_picked';
    case ItemsPacked = 'items_packed';
    case ReadyForDispatch = 'ready_for_dispatch';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case DeliveryAttemptFailed = 'delivery_attempt_failed';
    case Delivered = 'delivered';
    case ReadyForPickup = 'ready_for_pickup';
    case Collected = 'collected';
    case ExceptionRecorded = 'exception_recorded';
    case Cancelled = 'cancelled';
    case ProviderStatus = 'provider_status';
}
