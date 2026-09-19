<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Actions\AttributeValues\ArchiveAttributeValueAction;
use App\Domains\Catalog\Actions\AttributeValues\ChangeAttributeValueStatusAction;
use App\Domains\Catalog\Actions\AttributeValues\CreateAttributeValueAction;
use App\Domains\Catalog\Actions\AttributeValues\RestoreAttributeValueAction;
use App\Domains\Catalog\Actions\AttributeValues\UpdateAttributeValueAction;
use App\Domains\Catalog\DTOs\Attributes\AttributeTranslationData;
use App\Domains\Catalog\DTOs\Attributes\AttributeValueWriteData;
use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Queries\Attributes\AdminAttributeValueListQuery;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Attributes\AdminAttributeValueIndexRequest;
use App\Http\Requests\Api\V1\Admin\Attributes\ChangeAttributeValueStatusRequest;
use App\Http\Requests\Api\V1\Admin\Attributes\StoreAttributeValueRequest;
use App\Http\Requests\Api\V1\Admin\Attributes\UpdateAttributeValueRequest;
use App\Http\Resources\Api\V1\Admin\Attributes\AttributeValueDetailResource;
use App\Http\Resources\Api\V1\Admin\Attributes\AttributeValueListResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminAttributeValueController
{
    use AuthorizesRequests;

    public function index(
        AdminAttributeValueIndexRequest $request,
        Attribute $attribute,
        AdminAttributeValueListQuery $query,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', AttributeValue::class);

        return AttributeValueListResource::collection($query->paginate($attribute, $request->validated()))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function store(
        StoreAttributeValueRequest $request,
        Attribute $attribute,
        CreateAttributeValueAction $action,
    ): JsonResponse {
        $this->authorize('create', AttributeValue::class);

        /** @var User $actor */
        $actor = $request->user();
        $data = $this->toWriteData($request->validated());

        if ($data->status === AttributeValueStatus::Active) {
            $this->authorize('publish', new AttributeValue);
        }

        $value = $action->execute(
            $attribute,
            $data,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeValueDetailResource($value))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Attribute $attribute, AttributeValue $value): AttributeValueDetailResource
    {
        $this->authorize('view', $value);
        $this->assertOwnership($attribute, $value);
        $value->load(['translations', 'attribute']);

        return (new AttributeValueDetailResource($value))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function update(
        UpdateAttributeValueRequest $request,
        Attribute $attribute,
        AttributeValue $value,
        UpdateAttributeValueAction $action,
    ): AttributeValueDetailResource {
        $this->authorize('update', $value);
        $this->assertOwnership($attribute, $value);

        /** @var User $actor */
        $actor = $request->user();
        $data = $this->toWriteData($request->validated());

        if ($data->status === AttributeValueStatus::Active && $value->status !== AttributeValueStatus::Active) {
            $this->authorize('publish', $value);
        }

        $updated = $action->execute(
            $value,
            $data,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeValueDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function updateStatus(
        ChangeAttributeValueStatusRequest $request,
        Attribute $attribute,
        AttributeValue $value,
        ChangeAttributeValueStatusAction $action,
    ): AttributeValueDetailResource {
        $status = AttributeValueStatus::from((string) $request->validated('status'));

        if ($status === AttributeValueStatus::Active) {
            $this->authorize('publish', $value);
        } else {
            $this->authorize('update', $value);
        }

        $this->assertOwnership($attribute, $value);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $value,
            $status,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeValueDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function destroy(
        Request $request,
        Attribute $attribute,
        AttributeValue $value,
        ArchiveAttributeValueAction $action,
    ): AttributeValueDetailResource {
        $this->authorize('archive', $value);
        $this->assertOwnership($attribute, $value);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $value,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeValueDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function restore(
        Request $request,
        Attribute $attribute,
        int $value,
        RestoreAttributeValueAction $action,
    ): AttributeValueDetailResource {
        /** @var AttributeValue $model */
        $model = AttributeValue::withTrashed()->findOrFail($value);
        $this->authorize('restore', $model);
        $this->assertOwnership($attribute, $model);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $model,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeValueDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    private function assertOwnership(Attribute $attribute, AttributeValue $value): void
    {
        abort_unless((int) $value->attribute_id === (int) $attribute->id, 404);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function toWriteData(array $validated): AttributeValueWriteData
    {
        $translations = [];
        foreach ($validated['translations'] ?? [] as $row) {
            $translations[] = new AttributeTranslationData(
                locale: (string) $row['locale'],
                name: (string) $row['name'],
                description: isset($row['description']) ? (string) $row['description'] : null,
            );
        }

        /** @var array<string, mixed>|null $metadata */
        $metadata = $validated['metadata'] ?? null;

        return new AttributeValueWriteData(
            code: isset($validated['code']) ? (string) $validated['code'] : null,
            status: isset($validated['status']) ? AttributeValueStatus::from((string) $validated['status']) : null,
            sortOrder: array_key_exists('sort_order', $validated) ? (int) $validated['sort_order'] : null,
            colorHex: isset($validated['color_hex']) ? (string) $validated['color_hex'] : null,
            metadata: $metadata,
            translations: $translations,
            syncTranslations: array_key_exists('translations', $validated),
            colorHexProvided: array_key_exists('color_hex', $validated),
            metadataProvided: array_key_exists('metadata', $validated),
        );
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
