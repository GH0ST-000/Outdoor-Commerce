<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\DTOs\InventoryMutationResult;
use App\Domains\Inventory\DTOs\ReconcileStockCountData;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Domains\Inventory\Events\InventoryCountReconciled;
use App\Domains\Inventory\Exceptions\BalanceInvariantViolationException;
use App\Domains\Inventory\Models\InventoryBalance;
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

final class ReconcileStockCountAction
{
    public function __construct(
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
        ReconcileStockCountData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): InventoryMutationResult {
        if ($data->countedQuantity < 0) {
            throw new \InvalidArgumentException('Counted quantity cannot be negative.');
        }

        $warehouse = $this->guard->requireActiveWarehouse($data->warehouseId);
        $this->guard->requireActiveVariant($data->productVariantId);

        $payloadHash = PayloadHash::from([
            'warehouse_id' => $data->warehouseId,
            'product_variant_id' => $data->productVariantId,
            'counted_quantity' => $data->countedQuantity,
            'reason_code' => $data->reasonCode->value,
            'note' => $data->note,
            'expected_version' => $data->expectedVersion,
        ]);

        $actorId = (int) $actor->getAuthIdentifier();

        return $this->deadlockRetry->run(function () use ($data, $warehouse, $payloadHash, $actorId, $requestId, $ipAddress, $userAgent): InventoryMutationResult {
            return DB::transaction(function () use ($data, $warehouse, $payloadHash, $actorId, $requestId, $ipAddress, $userAgent): InventoryMutationResult {
                $claim = $this->idempotency->claimOperation(
                    $data->idempotencyKey,
                    $payloadHash,
                    InventoryOperationType::StockCount,
                    null,
                    null,
                    $data->reasonCode->value,
                    $data->note,
                    $actorId,
                    $requestId,
                );

                if ($claim->isReplay) {
                    $balance = InventoryBalance::query()
                        ->where('warehouse_id', $warehouse->id)
                        ->where('product_variant_id', $data->productVariantId)
                        ->firstOrFail();

                    return new InventoryMutationResult($claim->operation, collect([$balance]), true);
                }

                $balance = $this->balances->lockForUpdate((int) $warehouse->id, $data->productVariantId);
                $this->balances->assertExpectedVersion($balance, $data->expectedVersion);

                if ($data->countedQuantity < $balance->reserved) {
                    throw new BalanceInvariantViolationException('Counted quantity cannot be below reserved quantity.');
                }

                $before = $balance->quantities();
                $delta = $data->countedQuantity - $balance->on_hand;

                if ($delta !== 0) {
                    $movement = $delta > 0
                        ? InventoryMovementType::StockCountIn
                        : InventoryMovementType::StockCountOut;
                    $this->ledger->applyPhysicalDelta($claim->operation, $balance, $movement, $delta);
                    $balance->refresh();
                }

                /** @var list<callable(): void> $afterCommit */
                $afterCommit = [];
                if ($delta !== 0) {
                    $this->lowStock->evaluate($balance, $before, $afterCommit);
                }

                $this->recordAuditEvent->execute(new AuditEventData(
                    actorUserId: $actorId,
                    event: AuditEvent::InventoryStockCountReconciled,
                    subjectType: 'inventory_operation',
                    subjectId: (string) $claim->operation->id,
                    requestId: $requestId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    oldValues: ['on_hand' => $before->onHand, 'counted' => null],
                    newValues: ['on_hand' => $balance->on_hand, 'counted' => $data->countedQuantity, 'delta' => $delta],
                    metadata: null,
                ));

                $afterCommit[] = fn () => event(new InventoryCountReconciled(
                    $claim->operation->id,
                    (int) $warehouse->id,
                    $data->productVariantId,
                ));
                $afterCommit[] = fn () => $this->cache->bumpGlobal();
                $this->lowStock->registerAfterCommit($afterCommit);

                return new InventoryMutationResult($claim->operation, collect([$balance]), false);
            });
        }, $requestId);
    }
}
