<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

use App\Domains\Inventory\Enums\InventoryReasonCode;

final readonly class ReceiveInventoryData
{
    /**
     * @param  list<array{product_variant_id: int, quantity: int}>  $items
     */
    public function __construct(
        public int $warehouseId,
        public array $items,
        public InventoryReasonCode $reasonCode,
        public ?string $referenceType,
        public ?string $referenceId,
        public ?string $note,
        public string $idempotencyKey,
    ) {}
}
