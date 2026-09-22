<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Inventory\DTOs\PublicAvailabilityData;

final class CartAvailabilityService
{
    public function __construct(
        private readonly PublicInventoryAvailability $availability,
        private readonly CartValidationService $validation,
    ) {}

    /**
     * @param  list<int>  $variantIds
     * @return array<int, PublicAvailabilityData>
     */
    public function forVariants(array $variantIds): array
    {
        $ids = array_values(array_unique(array_filter($variantIds, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        return $this->availability->forVariants($ids);
    }

    public function maximumAllowed(PublicAvailabilityData $availability, bool $hasPrice): int
    {
        if (! $hasPrice) {
            return 0;
        }

        return min($this->validation->maxLineQuantity(), max(0, $availability->availableToSell));
    }
}
