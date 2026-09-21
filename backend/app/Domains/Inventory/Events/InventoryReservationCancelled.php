<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Events;

final readonly class InventoryReservationCancelled
{
    public function __construct(public int $reservationId) {}
}
