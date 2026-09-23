<?php

declare(strict_types=1);

namespace App\Domains\Cart\DTOs;

final readonly class CartLineAvailabilityData
{
    public function __construct(
        public bool $isAvailable,
        public bool $canIncrement,
        public bool $canDecrement,
        public int $maximumAllowedQuantity,
        public int $requestedQuantity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_available' => $this->isAvailable,
            'can_increment' => $this->canIncrement,
            'can_decrement' => $this->canDecrement,
            'maximum_allowed_quantity' => $this->maximumAllowedQuantity,
            'requested_quantity' => $this->requestedQuantity,
        ];
    }
}
