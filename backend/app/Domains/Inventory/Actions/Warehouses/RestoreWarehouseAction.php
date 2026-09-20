<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions\Warehouses;

use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;

final class RestoreWarehouseAction
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
        $warehouse->restore();
        $warehouse->updated_by = (int) $actor->getAuthIdentifier();
        $warehouse->save();

        $this->recordAuditEvent->execute(new AuditEventData(
            actorUserId: (int) $actor->getAuthIdentifier(),
            event: AuditEvent::WarehouseRestored,
            subjectType: 'warehouse',
            subjectId: (string) $warehouse->id,
            requestId: $requestId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            oldValues: null,
            newValues: ['deleted_at' => null],
            metadata: null,
        ));

        return $warehouse->fresh() ?? $warehouse;
    }
}
