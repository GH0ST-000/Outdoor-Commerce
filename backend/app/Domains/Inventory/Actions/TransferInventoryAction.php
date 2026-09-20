<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\DTOs\InventoryMutationResult;
use App\Domains\Inventory\DTOs\TransferInventoryData;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Domains\Inventory\Enums\InventoryReasonCode;
use App\Domains\Inventory\Events\InventoryTransferred;
use App\Domains\Inventory\Exceptions\TransferWarehousesMatchException;
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

final class TransferInventoryAction
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
        TransferInventoryData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): InventoryMutationResult {
        if ($data->sourceWarehouseId === $data->destinationWarehouseId) {
            throw new TransferWarehousesMatchException;
        }

        $source = $this->guard->requireActiveWarehouse($data->sourceWarehouseId);
        $destination = $this->guard->requireActiveWarehouse($data->destinationWarehouseId);

        $variantIds = array_column($data->items, 'product_variant_id');
        if (count($variantIds) !== count(array_unique($variantIds))) {
            throw new \InvalidArgumentException('Duplicate variant rows are not allowed.');
        }

        foreach ($variantIds as $variantId) {
            $this->guard->requireActiveVariant((int) $variantId);
        }

        $payloadHash = PayloadHash::from([
            'source_warehouse_id' => $data->sourceWarehouseId,
            'destination_warehouse_id' => $data->destinationWarehouseId,
            'items' => $data->items,
            'note' => $data->note,
        ]);

        $actorId = (int) $actor->getAuthIdentifier();

        return $this->deadlockRetry->run(function () use ($data, $source, $destination, $payloadHash, $actorId, $requestId, $ipAddress, $userAgent): InventoryMutationResult {
            return DB::transaction(function () use ($data, $source, $destination, $payloadHash, $actorId, $requestId, $ipAddress, $userAgent): InventoryMutationResult {
                $claim = $this->idempotency->claimOperation(
                    $data->idempotencyKey,
                    $payloadHash,
                    InventoryOperationType::Transfer,
                    'warehouse_transfer',
                    null,
                    InventoryReasonCode::Transfer->value,
                    $data->note,
                    $actorId,
                    $requestId,
                );

                if ($claim->isReplay) {
                    $balances = InventoryBalance::query()
                        ->whereIn('warehouse_id', [$source->id, $destination->id])
                        ->whereIn('product_variant_id', array_column($data->items, 'product_variant_id'))
                        ->get();

                    return new InventoryMutationResult($claim->operation, $balances, true);
                }

                $pairs = [];
                foreach ($data->items as $item) {
                    $pairs[] = ['warehouse_id' => (int) $source->id, 'product_variant_id' => (int) $item['product_variant_id']];
                    $pairs[] = ['warehouse_id' => (int) $destination->id, 'product_variant_id' => (int) $item['product_variant_id']];
                }

                $locked = $this->balances->lockMany($pairs);
                /** @var list<callable(): void> $afterCommit */
                $afterCommit = [];

                foreach ($data->items as $item) {
                    $qty = (int) $item['quantity'];
                    $variantId = (int) $item['product_variant_id'];
                    $sourceKey = $source->id.'-'.$variantId;
                    $destKey = $destination->id.'-'.$variantId;

                    /** @var InventoryBalance $sourceBalance */
                    $sourceBalance = $locked->get($sourceKey);
                    /** @var InventoryBalance $destBalance */
                    $destBalance = $locked->get($destKey);

                    $this->ledger->assertUnreservedAvailable($sourceBalance, $qty);

                    $sourceBefore = $sourceBalance->quantities();
                    $destBefore = $destBalance->quantities();

                    $this->ledger->applyPhysicalDelta($claim->operation, $sourceBalance, InventoryMovementType::TransferOut, -$qty);
                    $this->ledger->applyPhysicalDelta($claim->operation, $destBalance, InventoryMovementType::TransferIn, $qty);

                    $sourceBalance->refresh();
                    $destBalance->refresh();
                    $this->lowStock->evaluate($sourceBalance, $sourceBefore, $afterCommit);
                    $this->lowStock->evaluate($destBalance, $destBefore, $afterCommit);
                }

                $this->recordAuditEvent->execute(new AuditEventData(
                    actorUserId: $actorId,
                    event: AuditEvent::InventoryTransferred,
                    subjectType: 'inventory_operation',
                    subjectId: (string) $claim->operation->id,
                    requestId: $requestId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    oldValues: null,
                    newValues: [
                        'source_warehouse_id' => $source->id,
                        'destination_warehouse_id' => $destination->id,
                        'items' => $data->items,
                    ],
                    metadata: null,
                ));

                $afterCommit[] = fn () => event(new InventoryTransferred(
                    $claim->operation->id,
                    (int) $source->id,
                    (int) $destination->id,
                ));
                $afterCommit[] = fn () => $this->cache->bumpGlobal();
                $this->lowStock->registerAfterCommit($afterCommit);

                return new InventoryMutationResult($claim->operation, $locked->values(), false);
            });
        }, $requestId);
    }
}
