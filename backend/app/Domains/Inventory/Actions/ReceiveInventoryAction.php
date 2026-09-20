<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\DTOs\InventoryMutationResult;
use App\Domains\Inventory\DTOs\ReceiveInventoryData;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\InventoryOperationType;
use App\Domains\Inventory\Events\InventoryReceived;
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

final class ReceiveInventoryAction
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
        ReceiveInventoryData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): InventoryMutationResult {
        $warehouse = $this->guard->requireActiveWarehouse($data->warehouseId);
        $variantIds = array_column($data->items, 'product_variant_id');
        if (count($variantIds) !== count(array_unique($variantIds))) {
            throw new \InvalidArgumentException('Duplicate variant rows are not allowed.');
        }

        foreach ($variantIds as $variantId) {
            $this->guard->requireActiveVariant((int) $variantId);
        }

        $payloadHash = PayloadHash::from([
            'warehouse_id' => $data->warehouseId,
            'items' => $data->items,
            'reason_code' => $data->reasonCode->value,
            'reference_type' => $data->referenceType,
            'reference_id' => $data->referenceId,
            'note' => $data->note,
        ]);

        $actorId = (int) $actor->getAuthIdentifier();

        return $this->deadlockRetry->run(function () use ($data, $warehouse, $payloadHash, $actorId, $requestId, $ipAddress, $userAgent): InventoryMutationResult {
            return DB::transaction(function () use ($data, $warehouse, $payloadHash, $actorId, $requestId, $ipAddress, $userAgent): InventoryMutationResult {
                $claim = $this->idempotency->claimOperation(
                    $data->idempotencyKey,
                    $payloadHash,
                    InventoryOperationType::Receipt,
                    $data->referenceType,
                    $data->referenceId,
                    $data->reasonCode->value,
                    $data->note,
                    $actorId,
                    $requestId,
                );

                if ($claim->isReplay) {
                    $balances = InventoryBalance::query()
                        ->where('warehouse_id', $warehouse->id)
                        ->whereIn('product_variant_id', array_column($data->items, 'product_variant_id'))
                        ->get();

                    return new InventoryMutationResult($claim->operation, $balances, true);
                }

                $pairs = array_map(static fn (array $item): array => [
                    'warehouse_id' => (int) $warehouse->id,
                    'product_variant_id' => (int) $item['product_variant_id'],
                ], $data->items);

                $locked = $this->balances->lockMany($pairs);
                /** @var list<callable(): void> $afterCommit */
                $afterCommit = [];

                foreach ($data->items as $item) {
                    $key = $warehouse->id.'-'.$item['product_variant_id'];
                    /** @var InventoryBalance $balance */
                    $balance = $locked->get($key);
                    $before = $balance->quantities();
                    $this->ledger->applyPhysicalDelta(
                        $claim->operation,
                        $balance,
                        InventoryMovementType::Receipt,
                        (int) $item['quantity'],
                    );
                    $balance->refresh();
                    $this->lowStock->evaluate($balance, $before, $afterCommit);
                }

                $this->recordAuditEvent->execute(new AuditEventData(
                    actorUserId: $actorId,
                    event: AuditEvent::InventoryReceived,
                    subjectType: 'inventory_operation',
                    subjectId: (string) $claim->operation->id,
                    requestId: $requestId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    oldValues: null,
                    newValues: ['warehouse_id' => $warehouse->id, 'items' => $data->items],
                    metadata: ['operation_uuid' => $claim->operation->uuid],
                ));

                $afterCommit[] = fn () => event(new InventoryReceived($claim->operation->id, (int) $warehouse->id));
                $afterCommit[] = fn () => $this->cache->bumpGlobal();
                $this->lowStock->registerAfterCommit($afterCommit);

                return new InventoryMutationResult($claim->operation, $locked->values(), false);
            });
        }, $requestId);
    }
}
