<?php

declare(strict_types=1);

namespace App\Domains\Orders\Events;

final readonly class OrderReservationTransferred
{
    public const VERSION = 1;

    public function __construct(
        public string $orderPublicId,
        public string $quotePublicId,
        public int $reservationCount,
        public int $eventVersion = self::VERSION,
    ) {}
}
