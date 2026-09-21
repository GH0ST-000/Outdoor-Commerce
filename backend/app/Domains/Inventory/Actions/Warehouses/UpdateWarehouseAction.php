<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions\Warehouses;

use App\Domains\Inventory\DTOs\WarehouseWriteData;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Support\WarehouseCodeNormalizer;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class UpdateWarehouseAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(
        Warehouse $warehouse,
        WarehouseWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Warehouse {
        $actorId = (int) $actor->getAuthIdentifier();
        $old = $warehouse->only(['code', 'name', 'status', 'is_default']);

        return DB::transaction(function () use ($warehouse, $data, $actorId, $old, $requestId, $ipAddress, $userAgent): Warehouse {
            $warehouse->fill([
                'code' => WarehouseCodeNormalizer::normalize($data->code),
                'name' => $data->name,
                'status' => $data->status,
                'country_code' => strtoupper($data->countryCode),
                'city' => $data->city,
                'address_line_1' => $data->addressLine1,
                'address_line_2' => $data->addressLine2,
                'postal_code' => $data->postalCode,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'updated_by' => $actorId,
            ]);
            $warehouse->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::WarehouseUpdated,
                subjectType: 'warehouse',
                subjectId: (string) $warehouse->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: $old,
                newValues: $warehouse->only(['code', 'name', 'status', 'is_default']),
                metadata: null,
            ));

            return $warehouse->fresh() ?? $warehouse;
        });
    }
}
