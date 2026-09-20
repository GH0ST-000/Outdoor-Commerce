<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions;

use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Services\InventoryBalanceService;
use App\Domains\Inventory\Services\InventoryCache;
use App\Domains\Inventory\Services\InventoryLowStockService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class UpdateInventorySettingsAction
{
    public function __construct(
        private readonly InventoryBalanceService $balances,
        private readonly InventoryLowStockService $lowStock,
        private readonly InventoryCache $cache,
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(
        InventoryBalance $balance,
        int $safetyStock,
        int $reorderPoint,
        ?int $expectedVersion,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): InventoryBalance {
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($balance, $safetyStock, $reorderPoint, $expectedVersion, $actorId, $requestId, $ipAddress, $userAgent): InventoryBalance {
            $locked = $this->balances->lockForUpdate($balance->warehouse_id, $balance->product_variant_id);
            $this->balances->assertExpectedVersion($locked, $expectedVersion);
            $before = $locked->quantities();

            $locked->safety_stock = $safetyStock;
            $locked->reorder_point = $reorderPoint;
            $locked->version = $locked->version + 1;
            $locked->save();

            /** @var list<callable(): void> $afterCommit */
            $afterCommit = [];
            $this->lowStock->evaluate($locked, $before, $afterCommit);

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::InventorySettingsUpdated,
                subjectType: 'inventory_balance',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: [
                    'safety_stock' => $before->safetyStock,
                    'reorder_point' => $before->reorderPoint,
                ],
                newValues: [
                    'safety_stock' => $safetyStock,
                    'reorder_point' => $reorderPoint,
                ],
                metadata: null,
            ));

            $afterCommit[] = fn () => $this->cache->bumpGlobal();
            $this->lowStock->registerAfterCommit($afterCommit);

            return $locked->fresh() ?? $locked;
        });
    }
}
