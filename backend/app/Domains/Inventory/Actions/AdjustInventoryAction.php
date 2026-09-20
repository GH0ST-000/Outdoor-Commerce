<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\DTOs\AdjustInventoryData;
use App\Domains\Inventory\DTOs\InventoryMutationResultData;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Domains\Inventory\Events\InventoryAdjusted;
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

final class AdjustInventoryAction
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
        AdjustInventoryData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): InventoryMutationResultData {
        if ($data->quantityDelta === 0) {
            throw new \InvalidArgumentException('Adjustment delta cannot be zero.');
        }

        if ($data->reasonCode->requiresNote() && ($data->note === null || trim($data->note) === '')) {
            throw new \InvalidArgumentException('A note is required for this reason code.');
        }

        $warehouse = $this->guard->requireActiveWarehouse($data->warehouseId);
        $this->guard->requireActiveVariant($data->productVariantId);

        $payloadHash = PayloadHash::from([
            'warehouse_id' => $data->warehouseId,
            'product_variant_id' => $data->productVariantId,
            'quantity_delta' => $data->quantityDelta,
            'reason_code' => $data->reasonCode->value,
            'note' => $data->note,
            'expected_version' => $data->expectedVersion,
        ]);

        $actorId = (int) $actor->getAuthIdentifier();
        $movement = $data->quantityDelta > 0
            ? InventoryMovementType::AdjustmentIn
            : InventoryMovementType::AdjustmentOut;

        return $this->deadlockRetry->run(function () use ($data, $warehouse, $payloadHash, $actorId, $movement, $requestId, $ipAddress, $userAgent): InventoryMutationResultData {
            return DB::transaction(function () use ($data, $warehouse, $payloadHash, $actorId, $movement, $requestId, $ipAddress, $userAgent): InventoryMutationResultData {
                $claim = $this->idempotency->claimOperation(
                    $data->idempotencyKey,
                    $payloadHash,
                    InventoryOperationType::Adjustment,
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

                    return new InventoryMutationResultData($claim->operation, collect([$balance]), true);
                }

                $balance = $this->balances->lockForUpdate((int) $warehouse->id, $data->productVariantId);
                $this->balances->assertExpectedVersion($balance, $data->expectedVersion);
                $before = $balance->quantities();

                $this->ledger->applyPhysicalDelta(
                    $claim->operation,
                    $balance,
                    $movement,
                    $data->quantityDelta,
                );
                $balance->refresh();

                /** @var list<callable(): void> $afterCommit */
                $afterCommit = [];
                $this->lowStock->evaluate($balance, $before, $afterCommit);

                $this->recordAuditEvent->execute(new AuditEventData(
                    actorUserId: $actorId,
                    event: AuditEvent::InventoryAdjusted,
                    subjectType: 'inventory_operation',
                    subjectId: (string) $claim->operation->id,
                    requestId: $requestId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    oldValues: ['on_hand' => $before->onHand],
                    newValues: ['on_hand' => $balance->on_hand, 'delta' => $data->quantityDelta],
                    metadata: null,
                ));

                $afterCommit[] = fn () => event(new InventoryAdjusted(
                    $claim->operation->id,
                    (int) $warehouse->id,
                    $data->productVariantId,
                ));
                $afterCommit[] = fn () => $this->cache->bumpGlobal();
                $this->lowStock->registerAfterCommit($afterCommit);

                return new InventoryMutationResultData($claim->operation, collect([$balance]), false);
            });
        }, $requestId);
    }
}
