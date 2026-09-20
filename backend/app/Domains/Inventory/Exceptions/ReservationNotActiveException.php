<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

final class ReservationNotActiveException extends InventoryStateConflictException
{
    public function __construct(string $message = 'Reservation is not active.')
    {
        parent::__construct($message, 'RESERVATION_NOT_ACTIVE');
    }
}
