<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Events;

final readonly class CheckoutSessionCreated
{
    public function __construct(
        public string $sessionPublicId,
        public ?int $userId,
        public bool $guest,
    ) {}
}
