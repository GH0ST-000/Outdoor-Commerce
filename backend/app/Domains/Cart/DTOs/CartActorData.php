<?php

declare(strict_types=1);

namespace App\Domains\Cart\DTOs;

final readonly class CartActorData
{
    public function __construct(
        public ?int $userId,
        public ?string $rawGuestToken,
        public string $locale,
        public string $currency,
        public int $priceListId,
        public ?string $idempotencyKey = null,
        public ?string $endpoint = null,
        public ?int $expectedVersion = null,
        public ?string $issuedGuestToken = null,
    ) {}

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }
}
