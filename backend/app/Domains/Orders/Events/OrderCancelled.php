<?php

declare(strict_types=1);

namespace App\Domains\Orders\Events;

final readonly class OrderCancelled
{
    public const VERSION = 1;

    public function __construct(
        public string $orderPublicId,
        public string $orderNumber,
        public string $reasonCode,
        public int $eventVersion = self::VERSION,
    ) {}
}
