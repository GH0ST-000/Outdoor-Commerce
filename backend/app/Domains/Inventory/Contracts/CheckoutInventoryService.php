<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Contracts;

use App\Domains\Inventory\DTOs\ReserveInventoryData;
use App\Domains\Inventory\Models\InventoryReservation;

/**
 * Checkout/Orders integration surface (Day 10 foundation).
 */
interface CheckoutInventoryService
{
    public function reserve(ReserveInventoryData $data): InventoryReservation;

    public function release(InventoryReservation $reservation, string $idempotencyKey, ?string $reason = null): InventoryReservation;

    public function commit(InventoryReservation $reservation, string $idempotencyKey): InventoryReservation;

    public function reassignReference(
        InventoryReservation $reservation,
        string $referenceType,
        string $referenceId,
        \DateTimeInterface $expiresAt,
    ): InventoryReservation;
}
