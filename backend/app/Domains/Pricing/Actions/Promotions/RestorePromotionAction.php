<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Promotions;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class RestorePromotionAction
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
        $promotion->restore();
        $promotion->status = PromotionStatus::Draft;
        $promotion->updated_by = (int) $actor->getAuthIdentifier();
        $promotion->save();

        $this->recordAuditEvent->execute(new AuditEventData(
            actorUserId: (int) $actor->getAuthIdentifier(),
            event: AuditEvent::PromotionRestored,
            subjectType: 'promotion',
            subjectId: (string) $promotion->id,
            requestId: $requestId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            oldValues: null,
            newValues: ['deleted_at' => null, 'status' => PromotionStatus::Draft->value],
            metadata: null,
        ));

        DB::afterCommit(fn () => $this->cache->bumpGlobal());

        return $promotion->fresh(['targets']) ?? $promotion;
    }
}
