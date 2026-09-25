<?php

declare(strict_types=1);

namespace App\Domains\Orders\Enums;

/**
 * Aggregate order fulfillment. Shipment-level statuses live in Shipping.
 */
enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Processing = 'processing';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Exception = 'exception';

    public function hasStarted(): bool
    {
        return match ($this) {
            self::Unfulfilled, self::Cancelled => false,
            default => true,
        };
    }
}
