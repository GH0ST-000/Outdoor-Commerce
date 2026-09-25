<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Events;

final readonly class ShipmentCreated
{
    public const VERSION = 1;

    public function __construct(
        public string $shipmentPublicId,
        public string $orderPublicId,
        public int $eventVersion = self::VERSION,
    ) {}
}
