<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

final readonly class TransferInventoryData
{
    /**
     * @param  list<array{product_variant_id: int, quantity: int}>  $items
     */
    public function __construct(
        public int $sourceWarehouseId,
        public int $destinationWarehouseId,
        public array $items,
        public ?string $note,
        public string $idempotencyKey,
    ) {}
}
