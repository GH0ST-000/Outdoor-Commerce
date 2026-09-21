<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Prices;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\Events\PriceChanged;
use App\Domains\Pricing\Events\PricePublished;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Services\PriceScheduleService;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class PublishPricePeriodAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PriceScheduleService $schedule,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PricePeriod $period,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PricePeriod {
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($period, $actorId, $requestId, $ipAddress, $userAgent): PricePeriod {
            $published = $this->schedule->publishDraftPeriod($period, $actorId);
            $aggregate = $published->variantPrice;

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PricePeriodPublished,
                subjectType: 'price_period',
                subjectId: (string) $published->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['status' => $published->status->value],
                metadata: null,
            ));

            DB::afterCommit(function () use ($published, $aggregate): void {
                $this->cache->bumpGlobal();
                event(new PricePublished($published->id, $published->variant_price_id));
                if ($aggregate !== null) {
                    event(new PriceChanged($aggregate->id, $aggregate->price_list_id, $aggregate->product_variant_id));
                }
            });

            return $published;
        });
    }
}
