<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Enums;

enum InventoryReservationStatus: string
{
    case Active = 'active';
    case Committed = 'committed';
    case Released = 'released';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return $this !== self::Active;
    }

    public function contributesToReserved(): bool
    {
        return $this === self::Active;
    }
}
