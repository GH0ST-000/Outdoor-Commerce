<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Events;

final readonly class CheckoutFulfillmentSelected
{
    public function __construct(
        public string $sessionPublicId,
        public string $methodCode,
    ) {}
}
