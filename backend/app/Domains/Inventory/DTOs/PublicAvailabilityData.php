<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

/**
 * Aggregated sellable availability. Exact warehouse quantities stay internal.
 */
final readonly class PublicAvailabilityData
{
    public function __construct(
        public int $variantId,
        public int $availableToSell,
        public bool $isLowStock,
    ) {}

    public function isInStock(): bool
    {
        return $this->availableToSell > 0;
    }
}
