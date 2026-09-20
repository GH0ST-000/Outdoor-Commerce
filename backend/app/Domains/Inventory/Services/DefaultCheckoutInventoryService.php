<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Actions\CommitInventoryReservationAction;
use App\Domains\Inventory\Actions\ReleaseInventoryReservationAction;
use App\Domains\Inventory\Actions\ReserveInventoryAction;
use App\Domains\Inventory\Contracts\CheckoutInventoryService;
use App\Domains\Inventory\DTOs\ReserveInventoryData;
use App\Domains\Inventory\Models\InventoryReservation;

final class DefaultCheckoutInventoryService implements CheckoutInventoryService
{
    public function __construct(
        private readonly ReserveInventoryAction $reserveInventory,
        private readonly ReleaseInventoryReservationAction $releaseAction,
        private readonly CommitInventoryReservationAction $commitAction,
    ) {}

    public function reserve(ReserveInventoryData $data): InventoryReservation
    {
        return $this->reserveInventory->execute($data);
    }

    public function release(InventoryReservation $reservation, string $idempotencyKey, ?string $reason = null): InventoryReservation
    {
        $actor = auth()->user();
        if ($actor === null) {
            throw new \RuntimeException('Authenticated actor is required to release inventory.');
        }

        return $this->releaseAction->execute($reservation, $idempotencyKey, $reason, $actor);
    }

    public function commit(InventoryReservation $reservation, string $idempotencyKey): InventoryReservation
    {
        $actor = auth()->user();

        return $this->commitAction->execute($reservation, $idempotencyKey, $actor);
    }
}
