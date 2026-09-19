<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\AttributeValues;

use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Services\Attributes\AttributeTranslationSynchronizer;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeAttributeValueStatusAction
{
    public function __construct(
        private readonly AttributeTranslationSynchronizer $translations,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        AttributeValue $value,
        AttributeValueStatus $status,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AttributeValue {
        if ($status === AttributeValueStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => ['Use the archive endpoint to archive a value.'],
            ]);
        }

        $updated = DB::transaction(function () use ($value, $status, $actor, $requestId, $ipAddress, $userAgent): AttributeValue {
            /** @var AttributeValue $locked */
            $locked = AttributeValue::query()->whereKey($value->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $locked->status;

            if ($oldStatus === $status) {
                return $locked;
            }

            if ($status === AttributeValueStatus::Active) {
                $this->translations->assertGeorgianPresentForValue($locked);
            }

            $locked->status = $status;
            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeValueStatusChanged,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute_value',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $status->value],
            ));

            return $locked->fresh(['translations', 'attribute']) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
