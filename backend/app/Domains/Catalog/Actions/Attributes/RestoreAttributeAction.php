<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Attributes;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Restores a soft-deleted attribute to draft. Never auto-activates.
 */
final class RestoreAttributeAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        Attribute $attribute,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Attribute {
        $updated = DB::transaction(function () use ($attribute, $actor, $requestId, $ipAddress, $userAgent): Attribute {
            /** @var Attribute $locked */
            $locked = Attribute::withTrashed()->whereKey($attribute->id)->lockForUpdate()->firstOrFail();
            $locked->restore();
            $locked->status = AttributeStatus::Draft;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeRestored,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                newValues: ['status' => AttributeStatus::Draft->value],
            ));

            return $locked->fresh(['translations']) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
