<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventoryReservationTransition: string
{
    case Released = 'released';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Committed = 'committed';

    public function toStatus(): InventoryReservationStatus
    {
        return match ($this) {
            self::Released => InventoryReservationStatus::Released,
            self::Expired => InventoryReservationStatus::Expired,
            self::Cancelled => InventoryReservationStatus::Cancelled,
            self::Committed => InventoryReservationStatus::Committed,
        };
    }
}
