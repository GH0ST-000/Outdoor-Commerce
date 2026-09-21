<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Actions\Warehouses;

use App\Domains\Inventory\DTOs\WarehouseWriteData;
use App\Domains\Inventory\Enums\WarehouseStatus;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Support\WarehouseCodeNormalizer;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class CreateWarehouseAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function execute(
        WarehouseWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Warehouse {
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($data, $actorId, $requestId, $ipAddress, $userAgent): Warehouse {
            $hasDefault = Warehouse::query()->where('is_default', true)->whereNull('deleted_at')->exists();
            $isDefault = $data->isDefault || (! $hasDefault && $data->status === WarehouseStatus::Active);

            if ($isDefault) {
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $warehouse = Warehouse::query()->create([
                'code' => WarehouseCodeNormalizer::normalize($data->code),
                'name' => $data->name,
                'status' => $data->status,
                'is_default' => $isDefault,
                'country_code' => strtoupper($data->countryCode),
                'city' => $data->city,
                'address_line_1' => $data->addressLine1,
                'address_line_2' => $data->addressLine2,
                'postal_code' => $data->postalCode,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::WarehouseCreated,
                subjectType: 'warehouse',
                subjectId: (string) $warehouse->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['code' => $warehouse->code, 'name' => $warehouse->name],
                metadata: null,
            ));

            return $warehouse;
        });
    }
}
