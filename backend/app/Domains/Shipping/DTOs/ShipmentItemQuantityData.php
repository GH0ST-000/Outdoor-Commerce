<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

final readonly class ShipmentItemQuantityData
{
    public function __construct(
        public string $orderItemPublicId,
        public int $quantity,
    ) {}
}
