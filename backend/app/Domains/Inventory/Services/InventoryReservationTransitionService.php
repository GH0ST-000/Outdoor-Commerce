<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Enums\InventoryReservationTransition;
use App\Domains\Inventory\Events\InventoryReservationCommitted;
use App\Domains\Inventory\Events\InventoryReservationExpired;
use App\Domains\Inventory\Events\InventoryReservationReleased;
use App\Domains\Inventory\Exceptions\ReservationNotActiveException;
use App\Domains\Inventory\Models\InventoryOperation;
use App\Domains\Inventory\Models\InventoryReservation;
use Illuminate\Support\Str;

final class InventoryReservationTransitionService
{
    public function __construct(
        private readonly InventoryLedgerService $ledger,
        private readonly InventoryBalanceService $balances,
        private readonly InventoryLowStockService $lowStock,
    ) {}

    /**
     * @param  list<callable(): mixed>  $afterCommit
     */
    public function transition(
        InventoryReservation $reservation,
        InventoryReservationTransition $transition,
        ?string $releaseReason,
        ?InventoryOperation $commitOperation,
        array &$afterCommit,
    ): InventoryReservation {
        if ($reservation->status !== InventoryReservationStatus::Active) {
            if ($this->isIdempotentReplay($reservation, $transition)) {
                return $reservation;
            }

            throw new ReservationNotActiveException;
        }

        $balance = $this->balances->lockForUpdate($reservation->warehouse_id, $reservation->product_variant_id);
        $before = $balance->quantities();

        if ($transition === InventoryReservationTransition::Committed) {
            if ($commitOperation === null) {
                throw new \InvalidArgumentException('Commit operation is required.');
            }

            $this->ledger->applyReservedDelta($balance, -$reservation->quantity);
            $this->ledger->applyPhysicalDelta(
                $commitOperation,
                $balance,
                InventoryMovementType::Sale,
                -$reservation->quantity,
            );
        } else {
            $this->ledger->applyReservedDelta($balance, -$reservation->quantity);
        }

        $reservation->status = $transition->toStatus();
        $reservation->release_reason = $releaseReason;

        if ($transition === InventoryReservationTransition::Committed) {
            $reservation->committed_at = now();
        } else {
            $reservation->released_at = now();
        }

        $reservation->save();

        $this->lowStock->evaluate($balance, $before, $afterCommit);

        $afterCommit[] = match ($transition) {
            InventoryReservationTransition::Released, InventoryReservationTransition::Cancelled => fn () => event(new InventoryReservationReleased(
                $reservation->id,
                $reservation->warehouse_id,
                $reservation->product_variant_id,
                $reservation->quantity,
                $transition->value,
            )),
            InventoryReservationTransition::Expired => fn () => event(new InventoryReservationExpired(
                $reservation->id,
                $reservation->warehouse_id,
                $reservation->product_variant_id,
                $reservation->quantity,
            )),
            InventoryReservationTransition::Committed => fn () => event(new InventoryReservationCommitted(
                $reservation->id,
                $commitOperation->id,
                $reservation->warehouse_id,
                $reservation->product_variant_id,
                $reservation->quantity,
            )),
        };

        return $reservation->fresh() ?? $reservation;
    }

    public function createCommitOperation(
        string $idempotencyKey,
        string $payloadHash,
        ?int $performedBy,
        ?string $correlationId,
    ): InventoryOperation {
        return InventoryOperation::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => InventoryOperationType::ReservationCommitment,
            'idempotency_key' => $idempotencyKey,
            'payload_hash' => $payloadHash,
            'reference_type' => 'inventory_reservation',
            'reference_id' => null,
            'reason_code' => InventoryOperationType::ReservationCommitment->value,
            'note' => null,
            'performed_by' => $performedBy,
            'correlation_id' => $correlationId,
            'occurred_at' => now(),
        ]);
    }

    private function isIdempotentReplay(
        InventoryReservation $reservation,
        InventoryReservationTransition $transition,
    ): bool {
        return $reservation->status === $transition->toStatus();
    }
}
