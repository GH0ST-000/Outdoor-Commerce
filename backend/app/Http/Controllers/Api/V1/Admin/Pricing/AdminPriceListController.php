<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Actions\PriceLists\ArchivePriceListAction;
use App\Domains\Pricing\Actions\PriceLists\ChangePriceListStatusAction;
use App\Domains\Pricing\Actions\PriceLists\CreatePriceListAction;
use App\Domains\Pricing\Actions\PriceLists\RestorePriceListAction;
use App\Domains\Pricing\Actions\PriceLists\SetDefaultPriceListAction;
use App\Domains\Pricing\Actions\PriceLists\UpdatePriceListAction;
use App\Domains\Pricing\DTOs\PriceListWriteData;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Queries\AdminPriceListListQuery;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Pricing\AdminPriceListIndexRequest;
use App\Http\Requests\Api\V1\Admin\Pricing\ChangePriceListStatusRequest;
use App\Http\Requests\Api\V1\Admin\Pricing\StorePriceListRequest;
use App\Http\Requests\Api\V1\Admin\Pricing\UpdatePriceListRequest;
use App\Http\Resources\Api\V1\Admin\Pricing\PriceListResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminPriceListController
{
    use AuthorizesRequests;

    public function index(AdminPriceListIndexRequest $request, AdminPriceListListQuery $query): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PriceList::class);

        return PriceListResource::collection($query->paginate($request->validated()))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function store(StorePriceListRequest $request, CreatePriceListAction $action): JsonResponse
    {
        $this->authorize('create', PriceList::class);

        $list = $action->execute(
            $this->toWriteData($request->validated()),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PriceListResource($list))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, PriceList $priceList): PriceListResource
    {
        $this->authorize('view', $priceList);
        $priceList->loadCount('variantPrices as priced_variant_count');

        return (new PriceListResource($priceList))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList, UpdatePriceListAction $action): PriceListResource
    {
        $this->authorize('update', $priceList);

        $updated = $action->execute(
            $priceList,
            $this->toWriteData($request->validated()),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PriceListResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function updateStatus(
        ChangePriceListStatusRequest $request,
        PriceList $priceList,
        ChangePriceListStatusAction $action,
    ): PriceListResource {
        $this->authorize('publish', $priceList);

        $updated = $action->execute(
            $priceList,
            PriceListStatus::from($request->validated('status')),
            $request->validated('replacement_default_price_list_id'),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PriceListResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function updateDefault(Request $request, PriceList $priceList, SetDefaultPriceListAction $action): PriceListResource
    {
        $this->authorize('publish', $priceList);

        $updated = $action->execute(
            $priceList,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PriceListResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function destroy(Request $request, PriceList $priceList, ArchivePriceListAction $action): PriceListResource
    {
        $this->authorize('archive', $priceList);

        $archived = $action->execute(
            $priceList,
            $request->input('replacement_default_price_list_id') !== null
                ? (int) $request->input('replacement_default_price_list_id')
                : null,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PriceListResource($archived))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function restore(Request $request, int $priceList, RestorePriceListAction $action): PriceListResource
    {
        $model = PriceList::withTrashed()->findOrFail($priceList);
        $this->authorize('restore', $model);

        $restored = $action->execute(
            $model,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PriceListResource($restored))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function toWriteData(array $validated): PriceListWriteData
    {
        return new PriceListWriteData(
            code: (string) $validated['code'],
            name: (string) $validated['name'],
            currencyCode: (string) $validated['currency_code'],
            status: PriceListStatus::from((string) ($validated['status'] ?? PriceListStatus::Draft->value)),
            isDefault: (bool) ($validated['is_default'] ?? false),
            priority: (int) ($validated['priority'] ?? 0),
            pricesIncludeTax: (bool) ($validated['prices_include_tax'] ?? true),
        );
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
