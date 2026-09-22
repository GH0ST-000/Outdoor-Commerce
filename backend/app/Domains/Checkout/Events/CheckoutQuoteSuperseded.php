<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Events;

final readonly class CheckoutQuoteSuperseded
{
    public function __construct(
        public string $sessionPublicId,
        public string $quotePublicId,
        public int $revision,
    ) {}
}
