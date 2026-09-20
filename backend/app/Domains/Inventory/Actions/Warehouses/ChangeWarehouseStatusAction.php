<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions\Warehouses;

use App\Domains\Inventory\Enums\WarehouseStatus;
use App\Domains\Inventory\Exceptions\InventoryStateConflictException;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class ChangeWarehouseStatusAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(
        Warehouse $warehouse,
        WarehouseStatus $status,
        ?int $replacementDefaultWarehouseId,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Warehouse {
        $actorId = (int) $actor->getAuthIdentifier();
        $oldStatus = $warehouse->status;

        return DB::transaction(function () use ($warehouse, $status, $replacementDefaultWarehouseId, $actorId, $oldStatus, $requestId, $ipAddress, $userAgent): Warehouse {
            $warehouse = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();

            if ($status === WarehouseStatus::Archived && $warehouse->is_default) {
                if ($replacementDefaultWarehouseId === null) {
                    throw new InventoryStateConflictException(
                        'Default warehouse cannot be archived without selecting a replacement.',
                        'DEFAULT_WAREHOUSE_REPLACEMENT_REQUIRED',
                    );
                }

                $replacement = Warehouse::query()
                    ->whereKey($replacementDefaultWarehouseId)
                    ->where('status', WarehouseStatus::Active->value)
                    ->lockForUpdate()
                    ->firstOrFail();

                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
                $replacement->is_default = true;
                $replacement->save();
            }

            $warehouse->status = $status;
            $warehouse->updated_by = $actorId;
            $warehouse->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::WarehouseStatusChanged,
                subjectType: 'warehouse',
                subjectId: (string) $warehouse->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $status->value],
                metadata: null,
            ));

            return $warehouse;
        });
    }
}
