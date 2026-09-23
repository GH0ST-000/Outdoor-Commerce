<?php

declare(strict_types=1);

namespace App\Domains\Orders\Events;

final readonly class OrderReservationReleased
{
    public const VERSION = 1;

    public function __construct(
        public string $orderPublicId,
        public string $reason,
        public int $reservationCount,
        public int $eventVersion = self::VERSION,
    ) {}
}
