<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Events;

final readonly class CheckoutSessionExpired
{
    public function __construct(public string $sessionPublicId) {}
}
