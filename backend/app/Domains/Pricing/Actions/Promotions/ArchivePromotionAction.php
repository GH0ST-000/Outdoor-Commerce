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

final class ArchivePromotionAction
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
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($promotion, $actorId, $requestId, $ipAddress, $userAgent): Promotion {
            $promotion = Promotion::query()->whereKey($promotion->id)->lockForUpdate()->firstOrFail();
            $promotion->status = PromotionStatus::Archived;
            $promotion->updated_by = $actorId;
            $promotion->save();
            $promotion->delete();

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PromotionArchived,
                subjectType: 'promotion',
                subjectId: (string) $promotion->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: [
                    'status' => PromotionStatus::Archived->value,
                    'deleted_at' => now()->toIso8601String(),
                ],
                metadata: null,
            ));

            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $promotion;
        });
    }
}
