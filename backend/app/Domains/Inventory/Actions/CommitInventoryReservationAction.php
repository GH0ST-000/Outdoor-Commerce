<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Domains\Inventory\Enums\InventoryReservationTransition;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Services\InventoryCache;
use App\Domains\Inventory\Services\InventoryIdempotencyService;
use App\Domains\Inventory\Services\InventoryLowStockService;
use App\Domains\Inventory\Services\InventoryReservationTransitionService;
use App\Domains\Inventory\Support\DeadlockRetry;
use App\Domains\Inventory\Support\PayloadHash;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class CommitInventoryReservationAction
{
    public function __construct(
        private readonly InventoryReservationTransitionService $transitions,
        private readonly InventoryIdempotencyService $idempotency,
        private readonly InventoryLowStockService $lowStock,
        private readonly InventoryCache $cache,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly DeadlockRetry $deadlockRetry,
    ) {}

    public function execute(
        InventoryReservation $reservation,
        string $idempotencyKey,
        ?Authenticatable $actor = null,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): InventoryReservation {
        $payloadHash = PayloadHash::from([
            'reservation_id' => $reservation->id,
            'action' => 'commit',
        ]);

        $actorId = $actor !== null ? (int) $actor->getAuthIdentifier() : null;

        return $this->deadlockRetry->run(function () use ($reservation, $idempotencyKey, $payloadHash, $actorId, $requestId, $ipAddress, $userAgent): InventoryReservation {
            return DB::transaction(function () use ($reservation, $idempotencyKey, $payloadHash, $actorId, $requestId, $ipAddress, $userAgent): InventoryReservation {
                $claim = $this->idempotency->claimOperation(
                    $idempotencyKey,
                    $payloadHash,
                    InventoryOperationType::ReservationCommitment,
                    'inventory_reservation',
                    (string) $reservation->id,
                    InventoryOperationType::ReservationCommitment->value,
                    null,
                    $actorId,
                    $requestId,
                );

                $reservation = InventoryReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

                if ($claim->isReplay) {
                    return $reservation;
                }

                /** @var list<callable(): void> $afterCommit */
                $afterCommit = [];
                $updated = $this->transitions->transition(
                    $reservation,
                    InventoryReservationTransition::Committed,
                    null,
                    $claim->operation,
                    $afterCommit,
                );

                if ($actorId !== null) {
                    $this->recordAuditEvent->execute(new AuditEventData(
                        actorUserId: $actorId,
                        event: AuditEvent::InventoryReservationCommitted,
                        subjectType: 'inventory_reservation',
                        subjectId: (string) $reservation->id,
                        requestId: $requestId,
                        ipAddress: $ipAddress,
                        userAgent: $userAgent,
                        oldValues: null,
                        newValues: ['operation_id' => $claim->operation->id],
                        metadata: null,
                    ));
                }

                $afterCommit[] = fn () => $this->cache->bumpGlobal();
                $this->lowStock->registerAfterCommit($afterCommit);

                return $updated;
            });
        }, $requestId);
    }
}
