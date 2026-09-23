<?php

declare(strict_types=1);

namespace App\Domains\Orders\DTOs;

use App\Domains\Checkout\DTOs\CheckoutActorData;

final readonly class OrderActorData
{
    public function __construct(
        public CheckoutActorData $checkout,
        public ?string $rawOrderToken = null,
        public ?string $issuedOrderToken = null,
        public ?string $idempotencyKey = null,
        public ?string $endpoint = null,
    ) {}

    public function userId(): ?int
    {
        return $this->checkout->userId();
    }

    public function locale(): string
    {
        return $this->checkout->locale();
    }
}
