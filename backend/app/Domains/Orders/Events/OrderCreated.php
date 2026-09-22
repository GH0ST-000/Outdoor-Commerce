<?php

declare(strict_types=1);

namespace App\Domains\Orders\Events;

final readonly class OrderCreated
{
    public const VERSION = 1;

    public function __construct(
        public string $orderPublicId,
        public string $orderNumber,
        public string $quotePublicId,
        public string $checkoutSessionPublicId,
        public int $grandTotalMinor,
        public string $currency,
        public ?int $userId,
        public int $eventVersion = self::VERSION,
    ) {}
}
