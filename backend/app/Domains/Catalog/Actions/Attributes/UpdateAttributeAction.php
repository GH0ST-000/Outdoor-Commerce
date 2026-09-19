<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Attributes;

use App\Domains\Catalog\DTOs\Attributes\AttributeWriteData;
use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Services\Attributes\AttributeTranslationSynchronizer;
use App\Domains\Catalog\Services\Attributes\AttributeUsageService;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Support\AttributeCode;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateAttributeAction
{
    public function __construct(
        private readonly AttributeTranslationSynchronizer $translations,
        private readonly AttributeUsageService $usage,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        Attribute $attribute,
        AttributeWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Attribute {
        $updated = DB::transaction(function () use (
            $attribute,
            $data,
            $actor,
            $requestId,
            $ipAddress,
            $userAgent,
        ): Attribute {
            /** @var Attribute $locked */
            $locked = Attribute::query()->whereKey($attribute->id)->lockForUpdate()->firstOrFail();
            $old = [
                'code' => $locked->code,
                'type' => $locked->type->value,
                'status' => $locked->status->value,
                'is_filterable' => $locked->is_filterable,
                'sort_order' => $locked->sort_order,
            ];

            $inUse = $this->usage->attributeIsInUse($locked);

            if ($data->code !== null) {
                $code = AttributeCode::normalize($data->code);
                if (! AttributeCode::isValid($code)) {
                    throw ValidationException::withMessages([
                        'code' => ['A code must start with a letter and use only lowercase letters, digits, and underscores.'],
                    ]);
                }

                if ($code !== $locked->code) {
                    if ($inUse) {
                        throw ValidationException::withMessages([
                            'code' => ['The code is frozen because this attribute already has values or variants.'],
                        ]);
                    }

                    $taken = Attribute::withTrashed()
                        ->where('code', $code)
                        ->whereKeyNot($locked->id)
                        ->exists();

                    if ($taken) {
                        throw ValidationException::withMessages(['code' => ['This code is already taken.']]);
                    }

                    $locked->code = $code;
                }
            }

            if ($data->type !== null && $data->type !== $locked->type) {
                if ($inUse) {
                    throw ValidationException::withMessages([
                        'type' => ['The type is frozen because this attribute already has values or variants.'],
                    ]);
                }

                $locked->type = $data->type;
            }

            if ($data->isFilterable !== null) {
                $locked->is_filterable = $data->isFilterable;
            }

            if ($data->sortOrder !== null) {
                $locked->sort_order = max(0, $data->sortOrder);
            }

            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            if ($data->syncTranslations && $data->translations !== []) {
                $this->translations->syncAttribute($locked, $data->translations);
            }

            if ($data->status !== null && $data->status !== $locked->status) {
                if ($data->status === AttributeStatus::Archived) {
                    throw ValidationException::withMessages([
                        'status' => ['Use the archive endpoint to archive an attribute.'],
                    ]);
                }

                if ($data->status === AttributeStatus::Active) {
                    $this->translations->assertGeorgianPresent($locked);
                }

                $locked->status = $data->status;
                $locked->save();
            }

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeUpdated,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: $old,
                newValues: [
                    'code' => $locked->code,
                    'type' => $locked->type->value,
                    'status' => $locked->status->value,
                    'is_filterable' => $locked->is_filterable,
                    'sort_order' => $locked->sort_order,
                    'locales' => $locked->translations()->pluck('locale')->all(),
                ],
            ));

            return $locked->fresh(['translations']) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
