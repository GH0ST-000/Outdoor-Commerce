<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\DTOs\PublicAvailabilityData;

interface PublicInventoryAvailability
{
    public function cacheVersion(): int;

    public function forVariant(int $variantId): PublicAvailabilityData;

    /**
     * @param  list<int>  $variantIds
     * @return array<int, PublicAvailabilityData>
     */
    public function forVariants(array $variantIds): array;
}
