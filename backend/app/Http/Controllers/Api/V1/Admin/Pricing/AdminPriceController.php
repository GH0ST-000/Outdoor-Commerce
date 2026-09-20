<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Pricing;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Identity\Enums\Permission;
use App\Domains\Pricing\Actions\Prices\BulkUpsertPricesAction;
use App\Domains\Pricing\Actions\Prices\CancelPricePeriodAction;
use App\Domains\Pricing\Actions\Prices\CreateDraftPricePeriodAction;
use App\Domains\Pricing\Actions\Prices\PublishPricePeriodAction;
use App\Domains\Pricing\Actions\Prices\ReplaceEffectivePriceAction;
use App\Domains\Pricing\Actions\Prices\UpdateDraftPricePeriodAction;
use App\Domains\Pricing\DTOs\BulkUpsertPriceItemData;
use App\Domains\Pricing\DTOs\BulkUpsertPricesData;
use App\Domains\Pricing\DTOs\PricePeriodWriteData;
use App\Domains\Pricing\DTOs\ReplaceEffectivePriceData;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Pricing\Queries\AdminPriceIndexQuery;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Pricing\AdminPriceIndexRequest;
use App\Http\Requests\Api\V1\Admin\Pricing\BulkUpsertPricesRequest;
use App\Http\Resources\Api\V1\Admin\Pricing\PricePeriodResource;
use App\Http\Resources\Api\V1\Admin\Pricing\VariantPriceResource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class AdminPriceController
{
    use AuthorizesRequests;

    public function index(AdminPriceIndexRequest $request, AdminPriceIndexQuery $query): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PriceList::class);

        return VariantPriceResource::collection($query->paginate($request->validated()))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function showVariant(Request $request, PriceList $priceList, ProductVariant $variant): JsonResponse
    {
        $this->authorize('view', $priceList);

        $aggregate = VariantPrice::query()
            ->where('price_list_id', $priceList->id)
            ->where('product_variant_id', $variant->id)
            ->with([
                'priceList',
                'productVariant.product.translations',
                'periods' => fn ($q) => $q->orderByDesc('starts_at'),
            ])
            ->first();

        if ($aggregate === null) {
            return response()->json([
                'data' => [
                    'id' => 0,
                    'price_list_id' => $priceList->id,
                    'product_variant_id' => $variant->id,
                    'version' => 0,
                    'currency_code' => $priceList->currency_code,
                    'periods' => [],
                    'current_period' => null,
                    'scheduled_period' => null,
                    'effective_amount_minor' => null,
                    'pricing_ready' => false,
                ],
                'meta' => ['request_id' => $this->requestId($request)],
            ]);
        }

        $resource = (new VariantPriceResource($aggregate))->resolve();
        $resource['periods'] = PricePeriodResource::collection($aggregate->periods)->resolve();

        return response()->json([
            'data' => $resource,
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }

    public function store(Request $request, PriceList $priceList, ProductVariant $variant, CreateDraftPricePeriodAction $action): JsonResponse
    {
        $this->authorize('update', $priceList);

        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['sometimes', Rule::in(['draft'])],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $aggregate = $action->execute(
            $priceList,
            $variant,
            new PricePeriodWriteData(
                amountMinor: (int) $validated['amount_minor'],
                startsAt: CarbonImmutable::parse($validated['starts_at'])->utc(),
                endsAt: isset($validated['ends_at'])
                    ? CarbonImmutable::parse($validated['ends_at'])->utc()
                    : null,
                expectedVersion: isset($validated['expected_version'])
                    ? (int) $validated['expected_version']
                    : null,
            ),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new VariantPriceResource($aggregate->load([
            'priceList',
            'productVariant.product.translations',
            'periods' => fn ($q) => $q->orderByDesc('starts_at'),
        ])))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(201);
    }

    public function replace(Request $request, PriceList $priceList, ProductVariant $variant, ReplaceEffectivePriceAction $action): JsonResponse
    {
        $this->authorize('publish', $priceList);

        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'close_previous' => ['sometimes', 'boolean'],
            'confirm_replace' => ['sometimes', 'boolean'],
        ]);

        $aggregate = $action->execute(
            $priceList,
            $variant,
            new ReplaceEffectivePriceData(
                amountMinor: (int) $validated['amount_minor'],
                startsAt: CarbonImmutable::parse($validated['starts_at'])->utc(),
                endsAt: isset($validated['ends_at'])
                    ? CarbonImmutable::parse($validated['ends_at'])->utc()
                    : null,
                expectedVersion: (int) $validated['expected_version'],
                closePreviousAtStart: (bool) ($validated['close_previous'] ?? true),
            ),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new VariantPriceResource($aggregate->load([
            'priceList',
            'productVariant.product.translations',
            'periods' => fn ($q) => $q->orderByDesc('starts_at'),
        ])))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(201);
    }

    public function showPeriod(Request $request, PricePeriod $pricePeriod): PricePeriodResource
    {
        $this->authorize('view', $pricePeriod);

        return (new PricePeriodResource($pricePeriod->load('variantPrice.priceList')))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function updatePeriod(Request $request, PricePeriod $pricePeriod, UpdateDraftPricePeriodAction $action): PricePeriodResource
    {
        $this->authorize('manage', $pricePeriod);

        $validated = $request->validate([
            'amount_minor' => ['sometimes', 'integer', 'min:0'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $data = new PricePeriodWriteData(
            amountMinor: isset($validated['amount_minor'])
                ? (int) $validated['amount_minor']
                : $pricePeriod->amount_minor,
            startsAt: isset($validated['starts_at'])
                ? CarbonImmutable::parse($validated['starts_at'])->utc()
                : CarbonImmutable::instance($pricePeriod->starts_at)->utc(),
            endsAt: array_key_exists('ends_at', $validated)
                ? ($validated['ends_at'] !== null
                    ? CarbonImmutable::parse($validated['ends_at'])->utc()
                    : null)
                : ($pricePeriod->ends_at !== null
                    ? CarbonImmutable::instance($pricePeriod->ends_at)->utc()
                    : null),
            expectedVersion: isset($validated['expected_version'])
                ? (int) $validated['expected_version']
                : null,
        );

        $updated = $action->execute(
            $pricePeriod,
            $data,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PricePeriodResource($updated->load('variantPrice.priceList')))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function publishPeriod(Request $request, PricePeriod $pricePeriod, PublishPricePeriodAction $action): PricePeriodResource
    {
        $this->authorize('publish', $pricePeriod);

        $published = $action->execute(
            $pricePeriod,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PricePeriodResource($published->load('variantPrice.priceList')))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function cancelPeriod(Request $request, PricePeriod $pricePeriod, CancelPricePeriodAction $action): PricePeriodResource
    {
        $this->authorize('publish', $pricePeriod);

        $cancelled = $action->execute(
            $pricePeriod,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PricePeriodResource($cancelled->load('variantPrice.priceList')))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function bulk(BulkUpsertPricesRequest $request, BulkUpsertPricesAction $action): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::PricingPublish->value), 403);

        $validated = $request->validated();
        $items = [];
        foreach ($validated['items'] as $item) {
            $items[] = new BulkUpsertPriceItemData(
                priceListId: (int) $item['price_list_id'],
                productVariantId: (int) $item['product_variant_id'],
                amountMinor: (int) $item['amount_minor'],
                startsAt: isset($item['starts_at'])
                    ? CarbonImmutable::parse((string) $item['starts_at'])->utc()
                    : null,
                expectedVersion: array_key_exists('expected_version', $item) && $item['expected_version'] !== null
                    ? (int) $item['expected_version']
                    : null,
            );
        }

        $result = $action->execute(
            new BulkUpsertPricesData(
                items: $items,
                publish: (bool) ($validated['publish'] ?? true),
            ),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'data' => [
                'created' => $result->created,
                'updated' => $result->updated,
            ],
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
