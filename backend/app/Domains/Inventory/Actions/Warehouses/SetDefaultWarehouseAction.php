<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions\Warehouses;

use App\Domains\Inventory\Enums\WarehouseStatus;
use App\Domains\Inventory\Exceptions\WarehouseInactiveException;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class SetDefaultWarehouseAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(
        Warehouse $warehouse,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Warehouse {
        if (! $warehouse->allowsStockOperations()) {
            throw new WarehouseInactiveException('Only an active warehouse can be set as default.');
        }

        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($warehouse, $actorId, $requestId, $ipAddress, $userAgent): Warehouse {
            Warehouse::query()->where('is_default', true)->update(['is_default' => false]);

            $warehouse = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();
            $warehouse->is_default = true;
            $warehouse->status = WarehouseStatus::Active;
            $warehouse->updated_by = $actorId;
            $warehouse->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::WarehouseDefaultChanged,
                subjectType: 'warehouse',
                subjectId: (string) $warehouse->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['is_default' => true],
                metadata: null,
            ));

            return $warehouse;
        });
    }
}
