<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\AttributeValues;

use App\Domains\Catalog\DTOs\Attributes\AttributeValueWriteData;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Services\Attributes\AttributeTranslationSynchronizer;
use App\Domains\Catalog\Services\Attributes\AttributeValueColorService;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Support\AttributeCode;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAttributeValueAction
{
    public function __construct(
        private readonly AttributeTranslationSynchronizer $translations,
        private readonly AttributeValueColorService $colors,
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        Attribute $attribute,
        AttributeValueWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AttributeValue {
        $code = AttributeCode::normalize((string) $data->code);
        if (! AttributeCode::isValid($code)) {
            throw ValidationException::withMessages([
                'code' => ['A code must start with a letter and use only lowercase letters, digits, and underscores.'],
            ]);
        }

        $taken = AttributeValue::withTrashed()
            ->where('attribute_id', $attribute->id)
            ->where('code', $code)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['code' => ['This code is already taken for the attribute.']]);
        }

        if ($data->translations === []) {
            throw ValidationException::withMessages(['translations' => ['At least one translation is required.']]);
        }

        $colorHex = $this->colors->resolve($attribute, $data->colorHex, $data->colorHexProvided);

        $value = DB::transaction(function () use (
            $attribute,
            $code,
            $colorHex,
            $data,
            $actor,
            $requestId,
            $ipAddress,
            $userAgent,
        ): AttributeValue {
            $requestedStatus = $data->status ?? AttributeValueStatus::Draft;

            if ($requestedStatus === AttributeValueStatus::Archived) {
                throw ValidationException::withMessages([
                    'status' => ['Use the archive endpoint to archive a value.'],
                ]);
            }

            $value = new AttributeValue;
            $value->attribute_id = $attribute->id;
            $value->code = $code;
            $value->status = AttributeValueStatus::Draft;
            $value->sort_order = max(0, $data->sortOrder ?? 0);
            $value->color_hex = $colorHex;
            $value->metadata = $data->metadata;
            $value->created_by = (int) $actor->getAuthIdentifier();
            $value->updated_by = (int) $actor->getAuthIdentifier();
            $value->save();

            $this->translations->syncValue($value, $data->translations);

            if ($requestedStatus === AttributeValueStatus::Active) {
                $this->translations->assertGeorgianPresentForValue($value);
                $value->status = AttributeValueStatus::Active;
                $value->save();
            }

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AttributeValueCreated,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'attribute_value',
                subjectId: (string) $value->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                newValues: [
                    'attribute_id' => $value->attribute_id,
                    'code' => $value->code,
                    'status' => $value->status->value,
                    'color_hex' => $value->color_hex,
                    'locales' => $value->translations()->pluck('locale')->all(),
                ],
            ));

            return $value->fresh(['translations', 'attribute']) ?? $value;
        });

        $this->catalogCache->bump();

        return $value;
    }
}
