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

final class CancelInventoryReservationAction
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
        ?string $cancelReason,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): InventoryReservation {
        $payloadHash = PayloadHash::from([
            'reservation_id' => $reservation->id,
            'action' => 'cancel',
            'cancel_reason' => $cancelReason,
        ]);

        $actorId = (int) $actor->getAuthIdentifier();

        return $this->deadlockRetry->run(function () use ($reservation, $idempotencyKey, $payloadHash, $cancelReason, $actorId, $requestId, $ipAddress, $userAgent): InventoryReservation {
            return DB::transaction(function () use ($reservation, $idempotencyKey, $payloadHash, $cancelReason, $actorId, $requestId, $ipAddress, $userAgent): InventoryReservation {
                $claim = $this->idempotency->claimOperation(
                    $idempotencyKey,
                    $payloadHash,
                    InventoryOperationType::ReservationRelease,
                    'inventory_reservation',
                    (string) $reservation->id,
                    'cancelled',
                    $cancelReason,
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
                    InventoryReservationTransition::Cancelled,
                    $cancelReason,
                    null,
                    $afterCommit,
                );

                $this->recordAuditEvent->execute(new AuditEventData(
                    actorUserId: $actorId,
                    event: AuditEvent::InventoryReservationCancelled,
                    subjectType: 'inventory_reservation',
                    subjectId: (string) $reservation->id,
                    requestId: $requestId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    oldValues: null,
                    newValues: ['status' => InventoryReservationTransition::Cancelled->value],
                    metadata: null,
                ));

                $afterCommit[] = fn () => $this->cache->bumpGlobal();
                $this->lowStock->registerAfterCommit($afterCommit);

                return $updated;
            });
        }, $requestId);
    }
}
