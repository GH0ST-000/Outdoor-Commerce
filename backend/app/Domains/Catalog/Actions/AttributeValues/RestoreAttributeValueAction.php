<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\AttributeValues;

use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Restores a soft-deleted value to draft. Never auto-activates.
 */
final class RestoreAttributeValueAction
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
            $locked = AttributeValue::withTrashed()->whereKey($value->id)->lockForUpdate()->firstOrFail();
            $locked->restore();
            $locked->status = AttributeValueStatus::Draft;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeValueRestored,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute_value',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                newValues: ['status' => AttributeValueStatus::Draft->value],
            ));

            return $locked->fresh(['translations', 'attribute']) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
