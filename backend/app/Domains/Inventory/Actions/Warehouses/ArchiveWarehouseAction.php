<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions\Warehouses;

use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Enums\WarehouseStatus;
use App\Domains\Inventory\Exceptions\WarehouseHasActiveReservationsException;
use App\Domains\Inventory\Exceptions\WarehouseHasStockException;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class ArchiveWarehouseAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly ChangeWarehouseStatusAction $changeStatus,
    ) {}

    public function execute(
        Warehouse $warehouse,
        ?int $replacementDefaultWarehouseId,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Warehouse {
        $hasStock = InventoryBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('on_hand', '>', 0)
            ->exists();

        if ($hasStock) {
            throw new WarehouseHasStockException;
        }

        $hasReservations = InventoryReservation::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('status', InventoryReservationStatus::Active->value)
            ->exists();

        if ($hasReservations) {
            throw new WarehouseHasActiveReservationsException;
        }

        return DB::transaction(function () use ($warehouse, $replacementDefaultWarehouseId, $actor, $requestId, $ipAddress, $userAgent): Warehouse {
            $updated = $this->changeStatus->execute(
                $warehouse,
                WarehouseStatus::Archived,
                $replacementDefaultWarehouseId,
                $actor,
                $requestId,
                $ipAddress,
                $userAgent,
            );

            $updated->delete();

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: (int) $actor->getAuthIdentifier(),
                event: AuditEvent::WarehouseArchived,
                subjectType: 'warehouse',
                subjectId: (string) $warehouse->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['deleted_at' => now()->toIso8601String()],
                metadata: null,
            ));

            return $updated;
        });
    }
}
