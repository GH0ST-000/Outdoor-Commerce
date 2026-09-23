<?php

declare(strict_types=1);

namespace App\Domains\Payments\Events;

final readonly class InventoryCommittedForOrder
{
    public const VERSION = 1;

    public function __construct(
        public string $orderPublicId,
        public int $reservationCount,
        public int $eventVersion = self::VERSION,
    ) {}
}
