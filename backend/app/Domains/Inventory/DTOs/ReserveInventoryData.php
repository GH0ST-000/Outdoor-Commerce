<?php

declare(strict_types=1);

namespace App\Domains\Inventory\DTOs;

final readonly class ReserveInventoryData
{
    public function __construct(
        public int $productVariantId,
        public int $quantity,
        public ?int $warehouseId,
        public ?string $referenceType,
        public ?string $referenceId,
        public ?\DateTimeInterface $expiresAt,
        public string $idempotencyKey,
    ) {}
}
