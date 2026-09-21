<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Domains\Inventory\Actions\ReconcileStockCountAction;
use App\Domains\Inventory\DTOs\ReconcileStockCountData;
use App\Domains\Inventory\Enums\InventoryReasonCode;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Inventory\ReconcileStockCountRequest;
use App\Http\Resources\Api\V1\Admin\Inventory\InventoryBalanceResource;
use App\Http\Resources\Api\V1\Admin\Inventory\InventoryOperationResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminInventoryStockCountController
{
    use AuthorizesRequests;

    public function store(ReconcileStockCountRequest $request, ReconcileStockCountAction $action): JsonResponse
    {
        $this->authorize('adjust', InventoryBalance::class);
        $validated = $request->validated();

        $result = $action->execute(
            new ReconcileStockCountData(
                warehouseId: (int) $validated['warehouse_id'],
                productVariantId: (int) $validated['product_variant_id'],
                countedQuantity: (int) $validated['counted_quantity'],
                reasonCode: InventoryReasonCode::from($validated['reason_code']),
                note: $validated['note'] ?? null,
                expectedVersion: isset($validated['expected_version']) ? (int) $validated['expected_version'] : null,
                idempotencyKey: $request->idempotencyKey(),
            ),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        $balance = $result->balances->first();
        $balance?->load(['warehouse', 'variant.product.translations']);

        return response()->json([
            'data' => [
                'operation' => new InventoryOperationResource($result->operation),
                'balance' => $balance ? new InventoryBalanceResource($balance) : null,
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
