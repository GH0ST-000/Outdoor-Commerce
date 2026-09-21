<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\InventoryOperation;

final class OperationClaim
{
    private function __construct(
        public readonly InventoryOperation $operation,
        public readonly bool $isReplay,
    ) {}

    public static function fresh(InventoryOperation $operation): self
    {
        return new self($operation, false);
    }

    public static function replay(InventoryOperation $operation): self
    {
        return new self($operation, true);
    }
}
