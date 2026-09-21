<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Promotions;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Events\PromotionActivated;
use App\Domains\Pricing\Exceptions\PromotionNotReadyException;
use App\Domains\Pricing\Exceptions\PromotionTargetRequiredException;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class ActivatePromotionAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        Promotion $promotion,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Promotion {
        if (! in_array($promotion->status, [PromotionStatus::Draft, PromotionStatus::Paused], true)) {
            throw new PromotionNotReadyException('Only draft or paused promotions can be activated.');
        }

        $hasInclude = $promotion->targets()
            ->where('mode', PromotionTargetMode::Include)
            ->exists();

        if (! $hasInclude) {
            throw new PromotionTargetRequiredException;
        }

        $actorId = (int) $actor->getAuthIdentifier();

        $activated = DB::transaction(function () use ($promotion, $actorId): Promotion {
            $promotion->status = PromotionStatus::Active;
            $promotion->published_by = $actorId;
            $promotion->published_at = now('UTC')->toDateTimeString();
            $promotion->updated_by = $actorId;
            $promotion->save();

            return $promotion->fresh() ?? $promotion;
        });

        $this->recordAuditEvent->execute(new AuditEventData(
            actorUserId: $actorId,
            event: AuditEvent::PromotionActivated,
            subjectType: 'promotion',
            subjectId: (string) $activated->id,
            requestId: $requestId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            oldValues: null,
            newValues: ['status' => PromotionStatus::Active->value],
            metadata: null,
        ));

        $this->cache->bumpGlobal();

        DB::afterCommit(fn () => event(new PromotionActivated(promotionId: $activated->id)));

        return $activated;
    }
}
