<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Promotions;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\DTOs\PromotionWriteData;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Exceptions\PricingStateConflictException;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UpdatePromotionAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CreatePromotionAction $createPromotion,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        Promotion $promotion,
        PromotionWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Promotion {
        if (! in_array($promotion->status, [PromotionStatus::Draft, PromotionStatus::Paused], true)) {
            throw new PricingStateConflictException(
                'Only draft or paused promotions can be updated.',
                'PROMOTION_NOT_EDITABLE',
            );
        }

        $this->createPromotion->assertWriteData($data);
        $actorId = (int) $actor->getAuthIdentifier();
        $old = $promotion->only([
            'code', 'name', 'description', 'discount_type', 'percentage_basis_points',
            'fixed_amount_minor', 'currency_code', 'priority', 'stacking_mode', 'starts_at', 'ends_at',
            'maximum_discount_minor',
        ]);

        return DB::transaction(function () use ($promotion, $data, $actorId, $old, $requestId, $ipAddress, $userAgent): Promotion {
            $promotion->fill([
                'code' => Str::lower(trim($data->code)),
                'name' => $data->name,
                'description' => $data->description,
                'discount_type' => $data->discountType,
                'percentage_basis_points' => $data->percentageBasisPoints,
                'fixed_amount_minor' => $data->fixedAmountMinor,
                'currency_code' => $data->currencyCode !== null ? strtoupper($data->currencyCode) : null,
                'priority' => $data->priority,
                'stacking_mode' => $data->stackingMode,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'maximum_discount_minor' => $data->maximumDiscountMinor,
                'updated_by' => $actorId,
            ]);
            $promotion->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PromotionUpdated,
                subjectType: 'promotion',
                subjectId: (string) $promotion->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: $old,
                newValues: $promotion->only(array_keys($old)),
                metadata: null,
            ));

            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $promotion->fresh(['targets']) ?? $promotion;
        });
    }
}
