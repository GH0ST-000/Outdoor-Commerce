<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Preparing = 'preparing';
    case ReadyForPickup = 'ready_for_pickup';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function hasStarted(): bool
    {
        return match ($this) {
            self::Unfulfilled, self::Cancelled => false,
            default => true,
        };
    }
}
