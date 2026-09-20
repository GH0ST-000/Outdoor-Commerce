<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Promotions;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Events\PromotionPaused;
use App\Domains\Pricing\Exceptions\PromotionNotReadyException;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class PausePromotionAction
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
        if ($promotion->status !== PromotionStatus::Active) {
            throw new PromotionNotReadyException('Only active promotions can be paused.');
        }

        $actorId = (int) $actor->getAuthIdentifier();

        $paused = DB::transaction(function () use ($promotion, $actorId): Promotion {
            $promotion->status = PromotionStatus::Paused;
            $promotion->updated_by = $actorId;
            $promotion->save();

            return $promotion->fresh() ?? $promotion;
        });

        $this->recordAuditEvent->execute(new AuditEventData(
            actorUserId: $actorId,
            event: AuditEvent::PromotionPaused,
            subjectType: 'promotion',
            subjectId: (string) $paused->id,
            requestId: $requestId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            oldValues: ['status' => PromotionStatus::Active->value],
            newValues: ['status' => PromotionStatus::Paused->value],
            metadata: null,
        ));

        $this->cache->bumpGlobal();

        DB::afterCommit(fn () => event(new PromotionPaused(promotionId: $paused->id)));

        return $paused;
    }
}
