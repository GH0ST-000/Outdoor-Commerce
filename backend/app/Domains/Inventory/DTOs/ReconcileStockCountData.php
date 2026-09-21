<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

use App\Domains\Inventory\Enums\InventoryReasonCode;

final readonly class ReconcileStockCountData
{
    public function __construct(
        public int $warehouseId,
        public int $productVariantId,
        public int $countedQuantity,
        public InventoryReasonCode $reasonCode,
        public ?string $note,
        public ?int $expectedVersion,
        public string $idempotencyKey,
    ) {}
}
