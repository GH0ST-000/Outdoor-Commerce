<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Contracts\InventoryAllocationStrategy;
use App\Domains\Inventory\DTOs\ReserveInventoryData;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Events\InventoryReserved;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Services\InventoryBalanceService;
use App\Domains\Inventory\Services\InventoryCache;
use App\Domains\Inventory\Services\InventoryGuardService;
use App\Domains\Inventory\Services\InventoryIdempotencyService;
use App\Domains\Inventory\Services\InventoryLedgerService;
use App\Domains\Inventory\Services\InventoryLowStockService;
use App\Domains\Inventory\Support\DeadlockRetry;
use App\Domains\Inventory\Support\PayloadHash;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReserveInventoryAction
{
    public function __construct(
        private readonly InventoryAllocationStrategy $allocation,
        private readonly InventoryGuardService $guard,
        private readonly InventoryBalanceService $balances,
        private readonly InventoryLedgerService $ledger,
        private readonly InventoryIdempotencyService $idempotency,
        private readonly InventoryLowStockService $lowStock,
        private readonly InventoryCache $cache,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly DeadlockRetry $deadlockRetry,
    ) {}

    public function execute(
        ReserveInventoryData $data,
        ?Authenticatable $actor = null,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): InventoryReservation {
        $variantId = $this->guard->requireActiveVariant($data->productVariantId);

        $payloadHash = PayloadHash::from([
            'product_variant_id' => $data->productVariantId,
            'quantity' => $data->quantity,
            'warehouse_id' => $data->warehouseId,
            'reference_type' => $data->referenceType,
            'reference_id' => $data->referenceId,
            'expires_at' => $data->expiresAt?->format(DATE_ATOM),
        ]);

        $existing = $this->idempotency->claimReservation($data->idempotencyKey, $payloadHash);
        if ($existing !== null) {
            return $existing;
        }

        $actorId = $actor !== null ? (int) $actor->getAuthIdentifier() : null;
        $expiresAt = $data->expiresAt ?? now()->addMinutes((int) config('inventory.reservation_ttl_minutes', 15));

        return $this->deadlockRetry->run(function () use ($data, $variantId, $payloadHash, $actorId, $expiresAt, $requestId, $ipAddress, $userAgent): InventoryReservation {
            return DB::transaction(function () use ($data, $variantId, $payloadHash, $actorId, $expiresAt, $requestId, $ipAddress, $userAgent): InventoryReservation {
                $warehouse = $this->allocation->resolveWarehouse($data->warehouseId, $variantId, $data->quantity);
                $balance = $this->balances->lockForUpdate((int) $warehouse->id, $variantId);
                $before = $balance->quantities();
                $this->ledger->assertAvailableToSell($balance, $data->quantity);
                $this->ledger->applyReservedDelta($balance, $data->quantity);
                $balance->refresh();

                $reservation = InventoryReservation::query()->create([
                    'reservation_key' => (string) Str::uuid(),
                    'warehouse_id' => $warehouse->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $data->quantity,
                    'status' => InventoryReservationStatus::Active,
                    'reference_type' => $data->referenceType,
                    'reference_id' => $data->referenceId,
                    'idempotency_key' => $data->idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'expires_at' => $expiresAt,
                    'created_by' => $actorId,
                ]);

                /** @var list<callable(): void> $afterCommit */
                $afterCommit = [];
                $this->lowStock->evaluate($balance, $before, $afterCommit);

                if ($actorId !== null) {
                    $this->recordAuditEvent->execute(new AuditEventData(
                        actorUserId: $actorId,
                        event: AuditEvent::InventoryReservationCreated,
                        subjectType: 'inventory_reservation',
                        subjectId: (string) $reservation->id,
                        requestId: $requestId,
                        ipAddress: $ipAddress,
                        userAgent: $userAgent,
                        oldValues: null,
                        newValues: ['quantity' => $data->quantity, 'warehouse_id' => $warehouse->id],
                        metadata: ['reservation_key' => $reservation->reservation_key],
                    ));
                }

                $afterCommit[] = fn () => event(new InventoryReserved(
                    $reservation->id,
                    (int) $warehouse->id,
                    $variantId,
                    $data->quantity,
                ));
                $afterCommit[] = fn () => $this->cache->bumpGlobal();
                $this->lowStock->registerAfterCommit($afterCommit);

                return $reservation;
            });
        }, $requestId);
    }
}
