<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Attributes;

use App\Domains\Catalog\DTOs\Attributes\AttributeWriteData;
use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Services\Attributes\AttributeTranslationSynchronizer;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Support\AttributeCode;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAttributeAction
{
    public function __construct(
        private readonly AttributeTranslationSynchronizer $translations,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        AttributeWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Attribute {
        $code = AttributeCode::normalize((string) $data->code);
        if (! AttributeCode::isValid($code)) {
            throw ValidationException::withMessages([
                'code' => ['A code must start with a letter and use only lowercase letters, digits, and underscores.'],
            ]);
        }

        if ($data->type === null) {
            throw ValidationException::withMessages(['type' => ['An attribute type is required.']]);
        }

        if (Attribute::withTrashed()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => ['This code is already taken.']]);
        }

        if ($data->translations === []) {
            throw ValidationException::withMessages(['translations' => ['At least one translation is required.']]);
        }

        $attribute = DB::transaction(function () use ($code, $data, $actor, $requestId, $ipAddress, $userAgent): Attribute {
            $requestedStatus = $data->status ?? AttributeStatus::Draft;

            $attribute = new Attribute;
            $attribute->code = $code;
            $attribute->type = $data->type;
            // Persist as draft first so the Georgian baseline is checked against stored rows.
            $attribute->status = AttributeStatus::Draft;
            $attribute->is_filterable = $data->isFilterable ?? false;
            $attribute->sort_order = max(0, $data->sortOrder ?? 0);
            $attribute->created_by = (int) $actor->getAuthIdentifier();
            $attribute->updated_by = (int) $actor->getAuthIdentifier();
            $attribute->save();

            $this->translations->syncAttribute($attribute, $data->translations);

            if ($requestedStatus === AttributeStatus::Archived) {
                throw ValidationException::withMessages([
                    'status' => ['Use the archive endpoint to archive an attribute.'],
                ]);
            }

            if ($requestedStatus === AttributeStatus::Active) {
                $this->translations->assertGeorgianPresent($attribute);
                $attribute->status = AttributeStatus::Active;
                $attribute->save();
            }

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeCreated,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute',
                subjectId: (string) $attribute->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                newValues: [
                    'code' => $attribute->code,
                    'type' => $attribute->type->value,
                    'status' => $attribute->status->value,
                    'is_filterable' => $attribute->is_filterable,
                    'locales' => $attribute->translations()->pluck('locale')->all(),
                ],
            ));

            return $attribute->fresh(['translations']) ?? $attribute;
        });

        $this->catalogCache->bump();

        return $attribute;
    }
}
