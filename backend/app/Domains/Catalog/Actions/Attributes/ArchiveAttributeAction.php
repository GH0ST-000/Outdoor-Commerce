<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Attributes;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\ProductVariantStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Archives an attribute and soft-deletes it, mirroring product archiving.
 */
final class ArchiveAttributeAction
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
            $locked = Attribute::query()->whereKey($attribute->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $locked->status->value;

            $liveUsage = ProductVariant::query()
                ->where('status', '!=', ProductVariantStatus::Archived->value)
                ->whereHas('combinationRows', fn ($rows) => $rows->where('attribute_id', $locked->id))
                ->exists();

            if ($liveUsage) {
                throw ValidationException::withMessages([
                    'attribute' => ['This attribute is used by non-archived variants and cannot be archived.'],
                ]);
            }

            $locked->status = AttributeStatus::Archived;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();
            $locked->delete();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeArchived,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => AttributeStatus::Archived->value],
            ));

            return $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
