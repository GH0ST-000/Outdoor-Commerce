<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Events;

final readonly class CheckoutContactUpdated
{
    public function __construct(public string $sessionPublicId) {}
}
