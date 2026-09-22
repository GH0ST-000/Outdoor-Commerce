<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Events;

final readonly class InventoryReservedForQuote
{
    public function __construct(
        public string $quotePublicId,
        public int $lineCount,
    ) {}
}
