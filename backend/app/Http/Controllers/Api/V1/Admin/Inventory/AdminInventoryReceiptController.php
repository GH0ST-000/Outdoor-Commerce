<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Actions\ReceiveInventoryAction;
use App\Domains\Inventory\DTOs\ReceiveInventoryData;
use App\Domains\Inventory\Enums\InventoryReasonCode;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Inventory\ReceiveInventoryRequest;
use App\Http\Resources\Api\V1\Admin\Inventory\InventoryBalanceResource;
use App\Http\Resources\Api\V1\Admin\Inventory\InventoryOperationResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminInventoryReceiptController
{
    use AuthorizesRequests;

    public function store(ReceiveInventoryRequest $request, ReceiveInventoryAction $action): JsonResponse
    {
        $this->authorize('adjust', InventoryBalance::class);
        $validated = $request->validated();

        $result = $action->execute(
            new ReceiveInventoryData(
                warehouseId: (int) $validated['warehouse_id'],
                items: $validated['items'],
                reasonCode: InventoryReasonCode::from($validated['reason_code']),
                referenceType: $validated['reference_type'] ?? null,
                referenceId: $validated['reference_id'] ?? null,
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
