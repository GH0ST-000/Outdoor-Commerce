<?php

declare(strict_types=1);

namespace App\Domains\Checkout\DTOs;

use App\Domains\Cart\DTOs\CartActorData;

final readonly class CheckoutActorData
{
    public function __construct(
        public CartActorData $cartActor,
        public ?string $idempotencyKey = null,
        public ?string $endpoint = null,
        public ?int $expectedSessionVersion = null,
        public ?int $expectedCartVersion = null,
    ) {}

    public function userId(): ?int
    {
        return $this->cartActor->userId;
    }

    public function locale(): string
    {
        return $this->cartActor->locale;
    }

    public function currency(): string
    {
        return $this->cartActor->currency;
    }

    public function priceListId(): int
    {
        return $this->cartActor->priceListId;
    }
}
