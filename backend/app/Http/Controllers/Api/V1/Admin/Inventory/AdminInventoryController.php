<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Actions\UpdateInventorySettingsAction;
use App\Domains\Inventory\Models\InventoryBalance;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Queries\AdminInventoryLedgerQuery;
use App\Domains\Inventory\Queries\AdminInventoryListQuery;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Inventory\AdminInventoryIndexRequest;
use App\Http\Requests\Api\V1\Admin\Inventory\AdminInventoryLedgerIndexRequest;
use App\Http\Requests\Api\V1\Admin\Inventory\UpdateInventorySettingsRequest;
use App\Http\Resources\Api\V1\Admin\Inventory\InventoryBalanceResource;
use App\Http\Resources\Api\V1\Admin\Inventory\InventoryLedgerEntryResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminInventoryController
{
    use AuthorizesRequests;

    public function index(AdminInventoryIndexRequest $request, AdminInventoryListQuery $query): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InventoryBalance::class);

        return InventoryBalanceResource::collection($query->paginate($request->validated()))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function show(Request $request, Warehouse $warehouse, ProductVariant $variant): InventoryBalanceResource
    {
        $balance = $this->findOrCreateBalance($warehouse, $variant);
        $this->authorize('view', $balance);
        $balance->load(['warehouse', 'variant.product.translations']);

        return (new InventoryBalanceResource($balance))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function ledger(
        AdminInventoryLedgerIndexRequest $request,
        Warehouse $warehouse,
        ProductVariant $variant,
        AdminInventoryLedgerQuery $query,
    ): AnonymousResourceCollection {
        $balance = InventoryBalance::query()->where([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
        ])->first();

        if ($balance !== null) {
            $this->authorize('view', $balance);
        } else {
            $this->authorize('viewAny', InventoryBalance::class);
        }

        $filters = array_merge($request->validated(), [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
        ]);

        return InventoryLedgerEntryResource::collection($query->paginate($filters))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function updateSettings(
        UpdateInventorySettingsRequest $request,
        Warehouse $warehouse,
        ProductVariant $variant,
        UpdateInventorySettingsAction $action,
    ): InventoryBalanceResource {
        $balance = $this->findOrCreateBalance($warehouse, $variant);
        $this->authorize('manageSettings', $balance);

        $updated = $action->execute(
            $balance,
            (int) $request->validated('safety_stock'),
            (int) $request->validated('reorder_point'),
            $request->validated('expected_version') !== null ? (int) $request->validated('expected_version') : null,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        $updated->load(['warehouse', 'variant.product.translations']);

        return (new InventoryBalanceResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    private function findOrCreateBalance(Warehouse $warehouse, ProductVariant $variant): InventoryBalance
    {
        return InventoryBalance::query()->firstOrCreate([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
        ], [
            'on_hand' => 0,
            'reserved' => 0,
            'safety_stock' => 0,
            'reorder_point' => 0,
            'version' => 0,
        ]);
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
