<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Actions\Warehouses\ArchiveWarehouseAction;
use App\Domains\Inventory\Actions\Warehouses\ChangeWarehouseStatusAction;
use App\Domains\Inventory\Actions\Warehouses\CreateWarehouseAction;
use App\Domains\Inventory\Actions\Warehouses\RestoreWarehouseAction;
use App\Domains\Inventory\Actions\Warehouses\SetDefaultWarehouseAction;
use App\Domains\Inventory\Actions\Warehouses\UpdateWarehouseAction;
use App\Domains\Inventory\DTOs\WarehouseWriteData;
use App\Domains\Inventory\Enums\WarehouseStatus;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Queries\AdminWarehouseListQuery;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Inventory\AdminWarehouseIndexRequest;
use App\Http\Requests\Api\V1\Admin\Inventory\ChangeWarehouseStatusRequest;
use App\Http\Requests\Api\V1\Admin\Inventory\StoreWarehouseRequest;
use App\Http\Requests\Api\V1\Admin\Inventory\UpdateWarehouseRequest;
use App\Http\Resources\Api\V1\Admin\Inventory\WarehouseResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminWarehouseController
{
    use AuthorizesRequests;

    public function index(AdminWarehouseIndexRequest $request, AdminWarehouseListQuery $query): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Warehouse::class);

        return WarehouseResource::collection($query->paginate($request->validated()))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function store(StoreWarehouseRequest $request, CreateWarehouseAction $action): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = $action->execute(
            $this->toWriteData($request->validated()),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new WarehouseResource($warehouse))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('view', $warehouse);

        return (new WarehouseResource($warehouse))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse, UpdateWarehouseAction $action): WarehouseResource
    {
        $this->authorize('update', $warehouse);

        $updated = $action->execute(
            $warehouse,
            $this->toWriteData($request->validated()),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new WarehouseResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function updateStatus(
        ChangeWarehouseStatusRequest $request,
        Warehouse $warehouse,
        ChangeWarehouseStatusAction $action,
    ): WarehouseResource {
        $this->authorize('update', $warehouse);

        $updated = $action->execute(
            $warehouse,
            WarehouseStatus::from($request->validated('status')),
            $request->validated('replacement_default_warehouse_id'),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new WarehouseResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function updateDefault(Request $request, Warehouse $warehouse, SetDefaultWarehouseAction $action): WarehouseResource
    {
        $this->authorize('update', $warehouse);

        $updated = $action->execute(
            $warehouse,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new WarehouseResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function destroy(Request $request, Warehouse $warehouse, ArchiveWarehouseAction $action): WarehouseResource
    {
        $this->authorize('archive', $warehouse);

        $archived = $action->execute(
            $warehouse,
            $request->input('replacement_default_warehouse_id') !== null ? (int) $request->input('replacement_default_warehouse_id') : null,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new WarehouseResource($archived))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function restore(Request $request, int $warehouse, RestoreWarehouseAction $action): WarehouseResource
    {
        $model = Warehouse::withTrashed()->findOrFail($warehouse);
        $this->authorize('restore', $model);

        $restored = $action->execute(
            $model,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new WarehouseResource($restored))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function toWriteData(array $validated): WarehouseWriteData
    {
        return new WarehouseWriteData(
            code: (string) $validated['code'],
            name: (string) $validated['name'],
            status: WarehouseStatus::from((string) ($validated['status'] ?? WarehouseStatus::Active->value)),
            isDefault: (bool) ($validated['is_default'] ?? false),
            countryCode: (string) ($validated['country_code'] ?? 'GE'),
            city: $validated['city'] ?? null,
            addressLine1: $validated['address_line_1'] ?? null,
            addressLine2: $validated['address_line_2'] ?? null,
            postalCode: $validated['postal_code'] ?? null,
            latitude: isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            longitude: isset($validated['longitude']) ? (float) $validated['longitude'] : null,
        );
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
