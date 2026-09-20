<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Actions\CancelInventoryReservationAction;
use App\Domains\Inventory\Actions\ReleaseInventoryReservationAction;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Inventory\Queries\AdminInventoryReservationListQuery;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Inventory\AdminInventoryReservationIndexRequest;
use App\Http\Requests\Api\V1\Admin\Inventory\ReleaseInventoryReservationRequest;
use App\Http\Resources\Api\V1\Admin\Inventory\InventoryReservationResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminInventoryReservationController
{
    use AuthorizesRequests;

    public function index(
        AdminInventoryReservationIndexRequest $request,
        AdminInventoryReservationListQuery $query,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', InventoryReservation::class);

        return InventoryReservationResource::collection($query->paginate($request->validated()))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function show(Request $request, InventoryReservation $reservation): InventoryReservationResource
    {
        $this->authorize('view', $reservation);
        $reservation->load(['warehouse', 'variant']);

        return (new InventoryReservationResource($reservation))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function release(
        ReleaseInventoryReservationRequest $request,
        InventoryReservation $reservation,
        ReleaseInventoryReservationAction $action,
    ): InventoryReservationResource {
        $this->authorize('manage', $reservation);

        $updated = $action->execute(
            $reservation,
            $request->idempotencyKey(),
            $request->validated('release_reason'),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        $updated->load(['warehouse', 'variant']);

        return (new InventoryReservationResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function cancel(
        ReleaseInventoryReservationRequest $request,
        InventoryReservation $reservation,
        CancelInventoryReservationAction $action,
    ): InventoryReservationResource {
        $this->authorize('manage', $reservation);

        $updated = $action->execute(
            $reservation,
            $request->idempotencyKey(),
            $request->validated('release_reason'),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        $updated->load(['warehouse', 'variant']);

        return (new InventoryReservationResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
