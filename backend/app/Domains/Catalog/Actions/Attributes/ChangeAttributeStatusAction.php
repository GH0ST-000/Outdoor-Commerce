<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Attributes;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Services\Attributes\AttributeTranslationSynchronizer;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeAttributeStatusAction
{
    public function __construct(
        private readonly AttributeTranslationSynchronizer $translations,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        Attribute $attribute,
        AttributeStatus $status,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Attribute {
        if ($status === AttributeStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => ['Use the archive endpoint to archive an attribute.'],
            ]);
        }

        $updated = DB::transaction(function () use ($attribute, $status, $actor, $requestId, $ipAddress, $userAgent): Attribute {
            /** @var Attribute $locked */
            $locked = Attribute::query()->whereKey($attribute->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $locked->status;

            if ($oldStatus === $status) {
                return $locked;
            }

            if ($status === AttributeStatus::Active) {
                $this->translations->assertGeorgianPresent($locked);
            }

            $locked->status = $status;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeStatusChanged,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $status->value],
            ));

            return $locked->fresh(['translations']) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
