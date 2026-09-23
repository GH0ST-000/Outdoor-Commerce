<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Actions\CommitInventoryReservationAction;
use App\Domains\Inventory\Actions\ReassignInventoryReservationAction;
use App\Domains\Inventory\Actions\ReleaseInventoryReservationAction;
use App\Domains\Inventory\Actions\ReserveInventoryAction;
use App\Domains\Inventory\Contracts\CheckoutInventoryService;
use App\Domains\Inventory\DTOs\ReserveInventoryData;
use App\Domains\Inventory\Models\InventoryReservation;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class DefaultCheckoutInventoryService implements CheckoutInventoryService
{
    public function __construct(
        private readonly ReserveInventoryAction $reserveInventory,
        private readonly ReleaseInventoryReservationAction $releaseAction,
        private readonly CommitInventoryReservationAction $commitAction,
        private readonly ReassignInventoryReservationAction $reassignAction,
    ) {}

    public function reserve(ReserveInventoryData $data): InventoryReservation
    {
        return $this->reserveInventory->execute($data);
    }

    public function release(InventoryReservation $reservation, string $idempotencyKey, ?string $reason = null): InventoryReservation
    {
        return $this->releaseAction->execute($reservation, $idempotencyKey, $reason, auth()->user());
    }

    public function commit(InventoryReservation $reservation, string $idempotencyKey): InventoryReservation
    {
        $actor = auth()->user();

        return $this->commitAction->execute($reservation, $idempotencyKey, $actor);
    }

    public function reassignReference(
        InventoryReservation $reservation,
        string $referenceType,
        string $referenceId,
        \DateTimeInterface $expiresAt,
    ): InventoryReservation {
        $expires = $expiresAt instanceof CarbonInterface
            ? $expiresAt
            : Carbon::parse($expiresAt);

        return $this->reassignAction->execute($reservation, $referenceType, $referenceId, $expires);
    }
}
