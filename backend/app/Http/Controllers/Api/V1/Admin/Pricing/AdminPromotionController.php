<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Pricing;

use App\Domains\Pricing\Actions\Promotions\ActivatePromotionAction;
use App\Domains\Pricing\Actions\Promotions\ArchivePromotionAction;
use App\Domains\Pricing\Actions\Promotions\CreatePromotionAction;
use App\Domains\Pricing\Actions\Promotions\PausePromotionAction;
use App\Domains\Pricing\Actions\Promotions\PreviewPromotionAction;
use App\Domains\Pricing\Actions\Promotions\RestorePromotionAction;
use App\Domains\Pricing\Actions\Promotions\SyncPromotionTargetsAction;
use App\Domains\Pricing\Actions\Promotions\UpdatePromotionAction;
use App\Domains\Pricing\DTOs\PreviewPromotionData;
use App\Domains\Pricing\DTOs\PromotionTargetData;
use App\Domains\Pricing\DTOs\PromotionWriteData;
use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Queries\AdminPromotionListQuery;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Pricing\AdminPromotionIndexRequest;
use App\Http\Requests\Api\V1\Admin\Pricing\PreviewPromotionRequest;
use App\Http\Requests\Api\V1\Admin\Pricing\StorePromotionRequest;
use App\Http\Requests\Api\V1\Admin\Pricing\SyncPromotionTargetsRequest;
use App\Http\Requests\Api\V1\Admin\Pricing\UpdatePromotionRequest;
use App\Http\Resources\Api\V1\Admin\Pricing\PromotionResource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminPromotionController
{
    use AuthorizesRequests;

    public function index(AdminPromotionIndexRequest $request, AdminPromotionListQuery $query): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Promotion::class);

        return PromotionResource::collection($query->paginate($request->validated()))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function store(StorePromotionRequest $request, CreatePromotionAction $action): JsonResponse
    {
        $this->authorize('create', Promotion::class);

        $promotion = $action->execute(
            $this->toWriteData($request->validated()),
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PromotionResource($promotion->load('targets')))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Promotion $promotion): PromotionResource
    {
        $this->authorize('view', $promotion);

        return (new PromotionResource($promotion->load('targets')))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion, UpdatePromotionAction $action): PromotionResource
    {
        $this->authorize('update', $promotion);

        $validated = $request->validated();
        $data = new PromotionWriteData(
            code: (string) ($validated['code'] ?? $promotion->code),
            name: (string) ($validated['name'] ?? $promotion->name),
            description: array_key_exists('description', $validated)
                ? ($validated['description'] !== null ? (string) $validated['description'] : null)
                : $promotion->description,
            discountType: isset($validated['discount_type'])
                ? ($validated['discount_type'] instanceof DiscountType
                    ? $validated['discount_type']
                    : DiscountType::from((string) $validated['discount_type']))
                : $promotion->discount_type,
            percentageBasisPoints: array_key_exists('percentage_basis_points', $validated)
                ? ($validated['percentage_basis_points'] !== null ? (int) $validated['percentage_basis_points'] : null)
                : $promotion->percentage_basis_points,
            fixedAmountMinor: array_key_exists('fixed_amount_minor', $validated)
                ? ($validated['fixed_amount_minor'] !== null ? (int) $validated['fixed_amount_minor'] : null)
                : $promotion->fixed_amount_minor,
            currencyCode: array_key_exists('currency_code', $validated)
                ? ($validated['currency_code'] !== null ? (string) $validated['currency_code'] : null)
                : $promotion->currency_code,
            priority: isset($validated['priority']) ? (int) $validated['priority'] : $promotion->priority,
            stackingMode: isset($validated['stacking_mode'])
                ? ($validated['stacking_mode'] instanceof PromotionStackingMode
                    ? $validated['stacking_mode']
                    : PromotionStackingMode::from((string) $validated['stacking_mode']))
                : $promotion->stacking_mode,
            startsAt: isset($validated['starts_at'])
                ? CarbonImmutable::parse($validated['starts_at'])->utc()
                : CarbonImmutable::instance($promotion->starts_at)->utc(),
            endsAt: array_key_exists('ends_at', $validated)
                ? ($validated['ends_at'] !== null ? CarbonImmutable::parse($validated['ends_at'])->utc() : null)
                : ($promotion->ends_at !== null ? CarbonImmutable::instance($promotion->ends_at)->utc() : null),
            maximumDiscountMinor: array_key_exists('maximum_discount_minor', $validated)
                ? ($validated['maximum_discount_minor'] !== null ? (int) $validated['maximum_discount_minor'] : null)
                : $promotion->maximum_discount_minor,
        );

        $updated = $action->execute(
            $promotion,
            $data,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PromotionResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function activate(Request $request, Promotion $promotion, ActivatePromotionAction $action): PromotionResource
    {
        $this->authorize('publish', $promotion);

        $updated = $action->execute(
            $promotion,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PromotionResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function pause(Request $request, Promotion $promotion, PausePromotionAction $action): PromotionResource
    {
        $this->authorize('publish', $promotion);

        $updated = $action->execute(
            $promotion,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PromotionResource($updated->load('targets')))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function archive(Request $request, Promotion $promotion, ArchivePromotionAction $action): JsonResponse
    {
        $this->authorize('archive', $promotion);

        $action->execute(
            $promotion,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'data' => null,
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }

    public function restore(Request $request, int $promotion, RestorePromotionAction $action): PromotionResource
    {
        $model = Promotion::withTrashed()->findOrFail($promotion);
        $this->authorize('restore', $model);

        $restored = $action->execute(
            $model,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PromotionResource($restored))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function syncTargets(
        SyncPromotionTargetsRequest $request,
        Promotion $promotion,
        SyncPromotionTargetsAction $action,
    ): PromotionResource {
        $this->authorize('update', $promotion);

        $targets = [];
        foreach ($request->validated('targets') as $target) {
            $targets[] = new PromotionTargetData(
                targetType: $target['target_type'] instanceof PromotionTargetType
                    ? $target['target_type']
                    : PromotionTargetType::from((string) $target['target_type']),
                targetId: isset($target['target_id']) ? (int) $target['target_id'] : null,
                mode: $target['mode'] instanceof PromotionTargetMode
                    ? $target['mode']
                    : PromotionTargetMode::from((string) $target['mode']),
            );
        }

        $updated = $action->execute(
            $promotion,
            $targets,
            $request->user(),
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new PromotionResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function preview(PreviewPromotionRequest $request, PreviewPromotionAction $action): JsonResponse
    {
        $this->authorize('viewAny', Promotion::class);

        $result = $action->execute($this->toPreviewData($request->validated()));

        return response()->json([
            'data' => $result,
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }

    public function previewExisting(
        PreviewPromotionRequest $request,
        Promotion $promotion,
        PreviewPromotionAction $action,
    ): JsonResponse {
        $this->authorize('view', $promotion);

        $validated = $request->validated();
        $data = $this->toPreviewData($validated, $promotion->id);

        $result = $action->execute($data);

        return response()->json([
            'data' => $result,
            'meta' => ['request_id' => $this->requestId($request)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function toWriteData(array $validated): PromotionWriteData
    {
        $discountType = $validated['discount_type'] instanceof DiscountType
            ? $validated['discount_type']
            : DiscountType::from((string) $validated['discount_type']);
        $stackingMode = $validated['stacking_mode'] instanceof PromotionStackingMode
            ? $validated['stacking_mode']
            : PromotionStackingMode::from((string) $validated['stacking_mode']);

        return new PromotionWriteData(
            code: (string) $validated['code'],
            name: (string) $validated['name'],
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            discountType: $discountType,
            percentageBasisPoints: isset($validated['percentage_basis_points'])
                ? (int) $validated['percentage_basis_points']
                : null,
            fixedAmountMinor: isset($validated['fixed_amount_minor'])
                ? (int) $validated['fixed_amount_minor']
                : null,
            currencyCode: isset($validated['currency_code']) ? (string) $validated['currency_code'] : null,
            priority: (int) $validated['priority'],
            stackingMode: $stackingMode,
            startsAt: CarbonImmutable::parse($validated['starts_at'])->utc(),
            endsAt: array_key_exists('ends_at', $validated) && $validated['ends_at'] !== null
                ? CarbonImmutable::parse($validated['ends_at'])->utc()
                : null,
            maximumDiscountMinor: isset($validated['maximum_discount_minor'])
                ? (int) $validated['maximum_discount_minor']
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function toPreviewData(array $validated, ?int $existingPromotionId = null): PreviewPromotionData
    {
        $promotionWrite = null;
        if (isset($validated['promotion']) && is_array($validated['promotion'])) {
            $promo = $validated['promotion'];
            $discountType = isset($promo['discount_type'])
                ? ($promo['discount_type'] instanceof DiscountType
                    ? $promo['discount_type']
                    : DiscountType::from((string) $promo['discount_type']))
                : DiscountType::Percentage;
            $stackingMode = isset($promo['stacking_mode'])
                ? ($promo['stacking_mode'] instanceof PromotionStackingMode
                    ? $promo['stacking_mode']
                    : PromotionStackingMode::from((string) $promo['stacking_mode']))
                : PromotionStackingMode::Exclusive;

            $promotionWrite = new PromotionWriteData(
                code: (string) ($promo['code'] ?? 'preview'),
                name: (string) ($promo['name'] ?? 'Preview'),
                description: isset($promo['description']) ? (string) $promo['description'] : null,
                discountType: $discountType,
                percentageBasisPoints: isset($promo['percentage_basis_points'])
                    ? (int) $promo['percentage_basis_points']
                    : 1000,
                fixedAmountMinor: isset($promo['fixed_amount_minor'])
                    ? (int) $promo['fixed_amount_minor']
                    : null,
                currencyCode: isset($promo['currency_code']) ? (string) $promo['currency_code'] : 'GEL',
                priority: (int) ($promo['priority'] ?? 10),
                stackingMode: $stackingMode,
                startsAt: isset($promo['starts_at'])
                    ? CarbonImmutable::parse($promo['starts_at'])->utc()
                    : CarbonImmutable::now('UTC')->subHour(),
                endsAt: array_key_exists('ends_at', $promo) && $promo['ends_at'] !== null
                    ? CarbonImmutable::parse($promo['ends_at'])->utc()
                    : null,
                maximumDiscountMinor: isset($promo['maximum_discount_minor'])
                    ? (int) $promo['maximum_discount_minor']
                    : null,
            );
        }

        $targets = null;
        $targetSource = null;
        if (isset($validated['targets']) && is_array($validated['targets'])) {
            $targetSource = $validated['targets'];
        } elseif (isset($validated['promotion']['targets']) && is_array($validated['promotion']['targets'])) {
            $targetSource = $validated['promotion']['targets'];
        }

        if ($targetSource !== null) {
            $targets = [];
            foreach ($targetSource as $target) {
                $targets[] = new PromotionTargetData(
                    targetType: $target['target_type'] instanceof PromotionTargetType
                        ? $target['target_type']
                        : PromotionTargetType::from((string) $target['target_type']),
                    targetId: isset($target['target_id']) ? (int) $target['target_id'] : null,
                    mode: $target['mode'] instanceof PromotionTargetMode
                        ? $target['mode']
                        : PromotionTargetMode::from((string) $target['mode']),
                );
            }
        }

        /** @var list<int> $variantIds */
        $variantIds = array_map('intval', $validated['variant_ids'] ?? []);

        return new PreviewPromotionData(
            priceListId: isset($validated['price_list_id']) ? (int) $validated['price_list_id'] : null,
            variantIds: $variantIds,
            promotion: $promotionWrite,
            targets: $targets,
            existingPromotionId: $existingPromotionId,
        );
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
