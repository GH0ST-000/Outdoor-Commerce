<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Events;

final readonly class CheckoutQuoteCreated
{
    public function __construct(
        public string $sessionPublicId,
        public string $quotePublicId,
        public int $revision,
        public int $grandTotalMinor,
    ) {}
}
