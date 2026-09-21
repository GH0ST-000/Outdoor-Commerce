<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryOperation;
use Illuminate\Support\Collection;

final readonly class InventoryMutationResultData
{
    /**
     * @param  Collection<int, InventoryBalance>  $balances
     */
    public function __construct(
        public InventoryOperation $operation,
        public Collection $balances,
        public bool $isReplay,
    ) {}
}
