<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Actions\TransferInventoryAction;
use App\Domains\Inventory\DTOs\TransferInventoryData;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Inventory\TransferInventoryRequest;
use App\Http\Resources\Api\V1\Admin\Inventory\InventoryBalanceResource;
use App\Http\Resources\Api\V1\Admin\Inventory\InventoryOperationResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminInventoryTransferController
{
    use AuthorizesRequests;

    public function store(TransferInventoryRequest $request, TransferInventoryAction $action): JsonResponse
    {
        $this->authorize('transfer', InventoryBalance::class);
        $validated = $request->validated();

        $result = $action->execute(
            new TransferInventoryData(
                sourceWarehouseId: (int) $validated['source_warehouse_id'],
                destinationWarehouseId: (int) $validated['destination_warehouse_id'],
                items: $validated['items'],
                note: $validated['note'] ?? null,
                idempotencyKey: $request->idempotencyKey(),
            ),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        $result->balances->each->load(['warehouse', 'variant.product.translations']);

        return response()->json([
            'data' => [
                'operation' => new InventoryOperationResource($result->operation),
                'balances' => InventoryBalanceResource::collection($result->balances),
            ],
            'meta' => ['request_id' => $this->requestId($request), 'replay' => $result->isReplay],
        ], $result->isReplay ? 200 : 201);
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
