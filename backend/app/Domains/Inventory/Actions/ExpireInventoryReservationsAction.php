<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Enums\InventoryReservationTransition;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Services\InventoryCache;
use App\Domains\Inventory\Services\InventoryLowStockService;
use App\Domains\Inventory\Services\InventoryReservationTransitionService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;

final class ExpireInventoryReservationsAction
{
    public function __construct(
        private readonly InventoryReservationTransitionService $transitions,
        private readonly InventoryLowStockService $lowStock,
        private readonly InventoryCache $cache,
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(?int $chunkSize = null): int
    {
        $chunkSize = $chunkSize ?? (int) config('inventory.expire_chunk_size', 100);
        $processed = 0;

        $ids = InventoryReservation::query()
            ->where('status', InventoryReservationStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($chunkSize)
            ->pluck('id');

        foreach ($ids as $reservationId) {
            DB::transaction(function () use ($reservationId, &$processed): void {
                $reservation = InventoryReservation::query()->whereKey($reservationId)->lockForUpdate()->first();
                if ($reservation === null || $reservation->status !== InventoryReservationStatus::Active) {
                    return;
                }

                if ($reservation->expires_at === null || $reservation->expires_at->isFuture()) {
                    return;
                }

                /** @var list<callable(): void> $afterCommit */
                $afterCommit = [];
                $this->transitions->transition(
                    $reservation,
                    InventoryReservationTransition::Expired,
                    'expired',
                    null,
                    $afterCommit,
                );

                $this->recordAuditEvent->execute(new AuditEventData(
                    actorUserId: null,
                    event: AuditEvent::InventoryReservationExpired,
                    subjectType: 'inventory_reservation',
                    subjectId: (string) $reservation->id,
                    requestId: null,
                    ipAddress: null,
                    userAgent: null,
                    oldValues: null,
                    newValues: ['status' => InventoryReservationTransition::Expired->value],
                    metadata: null,
                ));

                $afterCommit[] = fn () => $this->cache->bumpGlobal();
                $this->lowStock->registerAfterCommit($afterCommit);
                $processed++;
            });
        }

        return $processed;
    }
}
