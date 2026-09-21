<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

use App\Domains\Inventory\Enums\InventoryReasonCode;

final readonly class AdjustInventoryData
{
    public function __construct(
        public int $warehouseId,
        public int $productVariantId,
        public int $quantityDelta,
        public InventoryReasonCode $reasonCode,
        public ?string $note,
        public ?int $expectedVersion,
        public string $idempotencyKey,
    ) {}
}
