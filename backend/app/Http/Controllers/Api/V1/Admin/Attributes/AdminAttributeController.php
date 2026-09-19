<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Attributes;

use App\Domains\Catalog\Actions\Attributes\ArchiveAttributeAction;
use App\Domains\Catalog\Actions\Attributes\ChangeAttributeStatusAction;
use App\Domains\Catalog\Actions\Attributes\CreateAttributeAction;
use App\Domains\Catalog\Actions\Attributes\RestoreAttributeAction;
use App\Domains\Catalog\Actions\Attributes\UpdateAttributeAction;
use App\Domains\Catalog\DTOs\Attributes\AttributeTranslationData;
use App\Domains\Catalog\DTOs\Attributes\AttributeWriteData;
use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Queries\Attributes\AdminAttributeListQuery;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Attributes\AdminAttributeIndexRequest;
use App\Http\Requests\Api\V1\Admin\Attributes\ChangeAttributeStatusRequest;
use App\Http\Requests\Api\V1\Admin\Attributes\StoreAttributeRequest;
use App\Http\Requests\Api\V1\Admin\Attributes\UpdateAttributeRequest;
use App\Http\Resources\Api\V1\Admin\Attributes\AttributeDetailResource;
use App\Http\Resources\Api\V1\Admin\Attributes\AttributeListResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminAttributeController
{
    use AuthorizesRequests;

    public function index(
        AdminAttributeIndexRequest $request,
        AdminAttributeListQuery $query,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', Attribute::class);

        return AttributeListResource::collection($query->paginate($request->validated()))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function store(StoreAttributeRequest $request, CreateAttributeAction $action): JsonResponse
    {
        $this->authorize('create', Attribute::class);

        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $data = $this->toWriteData($validated);

        if ($data->status === AttributeStatus::Active) {
            $this->authorize('publish', new Attribute);
        }

        $attribute = $action->execute(
            $data,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeDetailResource($attribute))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Attribute $attribute): AttributeDetailResource
    {
        $this->authorize('view', $attribute);
        $attribute->load('translations');

        return (new AttributeDetailResource($attribute))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function update(
        UpdateAttributeRequest $request,
        Attribute $attribute,
        UpdateAttributeAction $action,
    ): AttributeDetailResource {
        $this->authorize('update', $attribute);

        /** @var User $actor */
        $actor = $request->user();
        $data = $this->toWriteData($request->validated());

        if ($data->status === AttributeStatus::Active && $attribute->status !== AttributeStatus::Active) {
            $this->authorize('publish', $attribute);
        }

        $updated = $action->execute(
            $attribute,
            $data,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function updateStatus(
        ChangeAttributeStatusRequest $request,
        Attribute $attribute,
        ChangeAttributeStatusAction $action,
    ): AttributeDetailResource {
        $status = AttributeStatus::from((string) $request->validated('status'));

        if ($status === AttributeStatus::Active) {
            $this->authorize('publish', $attribute);
        } else {
            $this->authorize('update', $attribute);
        }

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $attribute,
            $status,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function destroy(
        Request $request,
        Attribute $attribute,
        ArchiveAttributeAction $action,
    ): AttributeDetailResource {
        $this->authorize('archive', $attribute);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $attribute,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function restore(
        Request $request,
        int $attribute,
        RestoreAttributeAction $action,
    ): AttributeDetailResource {
        $model = Attribute::withTrashed()->findOrFail($attribute);
        $this->authorize('restore', $model);

        /** @var User $actor */
        $actor = $request->user();
        $updated = $action->execute(
            $model,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new AttributeDetailResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function toWriteData(array $validated): AttributeWriteData
    {
        $translations = [];
        foreach ($validated['translations'] ?? [] as $row) {
            $translations[] = new AttributeTranslationData(
                locale: (string) $row['locale'],
                name: (string) $row['name'],
                description: isset($row['description']) ? (string) $row['description'] : null,
            );
        }

        return new AttributeWriteData(
            code: isset($validated['code']) ? (string) $validated['code'] : null,
            type: isset($validated['type']) ? AttributeType::from((string) $validated['type']) : null,
            status: isset($validated['status']) ? AttributeStatus::from((string) $validated['status']) : null,
            isFilterable: array_key_exists('is_filterable', $validated) ? (bool) $validated['is_filterable'] : null,
            sortOrder: array_key_exists('sort_order', $validated) ? (int) $validated['sort_order'] : null,
            translations: $translations,
            syncTranslations: array_key_exists('translations', $validated),
        );
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
