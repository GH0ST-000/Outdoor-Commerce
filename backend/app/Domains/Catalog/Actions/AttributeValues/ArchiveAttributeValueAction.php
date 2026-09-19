<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\AttributeValues;

use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArchiveAttributeValueAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        AttributeValue $value,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AttributeValue {
        $updated = DB::transaction(function () use ($value, $actor, $requestId, $ipAddress, $userAgent): AttributeValue {
            /** @var AttributeValue $locked */
            $locked = AttributeValue::query()->whereKey($value->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $locked->status->value;

            $liveUsage = ProductVariant::query()
                ->where('status', '!=', ProductVariantStatus::Archived->value)
                ->whereHas('combinationRows', fn ($rows) => $rows->where('attribute_value_id', $locked->id))
                ->exists();

            if ($liveUsage) {
                throw ValidationException::withMessages([
                    'attribute_value' => ['This value is used by non-archived variants and cannot be archived.'],
                ]);
            }

            $locked->status = AttributeValueStatus::Archived;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();
            $locked->delete();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeValueArchived,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute_value',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => AttributeValueStatus::Archived->value],
            ));

            return $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
