<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\AttributeValues;

use App\Domains\Catalog\DTOs\Attributes\AttributeValueWriteData;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Services\Attributes\AttributeTranslationSynchronizer;
use App\Domains\Catalog\Services\Attributes\AttributeUsageService;
use App\Domains\Catalog\Services\Attributes\AttributeValueColorService;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Support\AttributeCode;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateAttributeValueAction
{
    public function __construct(
        private readonly AttributeTranslationSynchronizer $translations,
        private readonly AttributeValueColorService $colors,
        private readonly AttributeUsageService $usage,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        AttributeValue $value,
        AttributeValueWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AttributeValue {
        $updated = DB::transaction(function () use ($value, $data, $actor, $requestId, $ipAddress, $userAgent): AttributeValue {
            /** @var AttributeValue $locked */
            $locked = AttributeValue::query()->whereKey($value->id)->lockForUpdate()->firstOrFail();
            $locked->load('attribute');
            $attribute = $locked->attribute;

            if ($attribute === null) {
                throw ValidationException::withMessages(['attribute_id' => ['The parent attribute is missing.']]);
            }

            $old = [
                'code' => $locked->code,
                'status' => $locked->status->value,
                'sort_order' => $locked->sort_order,
                'color_hex' => $locked->color_hex,
            ];

            if ($data->code !== null) {
                $code = AttributeCode::normalize($data->code);
                if (! AttributeCode::isValid($code)) {
                    throw ValidationException::withMessages([
                        'code' => ['A code must start with a letter and use only lowercase letters, digits, and underscores.'],
                    ]);
                }

                if ($code !== $locked->code) {
                    if ($this->usage->valueIsInUse($locked)) {
                        throw ValidationException::withMessages([
                            'code' => ['The code is frozen because variants already use this value.'],
                        ]);
                    }

                    $taken = AttributeValue::withTrashed()
                        ->where('attribute_id', $locked->attribute_id)
                        ->where('code', $code)
                        ->whereKeyNot($locked->id)
                        ->exists();

                    if ($taken) {
                        throw ValidationException::withMessages([
                            'code' => ['This code is already taken for the attribute.'],
                        ]);
                    }

                    $locked->code = $code;
                }
            }

            if ($data->sortOrder !== null) {
                $locked->sort_order = max(0, $data->sortOrder);
            }

            if ($data->colorHexProvided) {
                $locked->color_hex = $this->colors->resolve($attribute, $data->colorHex, true);
            }

            if ($data->metadataProvided) {
                $locked->metadata = $data->metadata;
            }

            $locked->updated_by = (int) $actor->getAuthIdentifier();
            $locked->save();

            if ($data->syncTranslations && $data->translations !== []) {
                $this->translations->syncValue($locked, $data->translations);
            }

            if ($data->status !== null && $data->status !== $locked->status) {
                if ($data->status === AttributeValueStatus::Archived) {
                    throw ValidationException::withMessages([
                        'status' => ['Use the archive endpoint to archive a value.'],
                    ]);
                }

                if ($data->status === AttributeValueStatus::Active) {
                    $this->translations->assertGeorgianPresentForValue($locked);
                }

                $locked->status = $data->status;
                $locked->save();
            }

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeValueUpdated,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute_value',
                subjectId: (string) $locked->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: $old,
                newValues: [
                    'code' => $locked->code,
                    'status' => $locked->status->value,
                    'sort_order' => $locked->sort_order,
                    'color_hex' => $locked->color_hex,
                    'locales' => $locked->translations()->pluck('locale')->all(),
                ],
            ));

            return $locked->fresh(['translations', 'attribute']) ?? $locked;
        });

        $this->catalogCache->bump();

        return $updated;
    }
}
