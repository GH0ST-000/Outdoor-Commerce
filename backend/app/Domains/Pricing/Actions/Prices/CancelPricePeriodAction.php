<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Prices;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\Events\PriceCancelled;
use App\Domains\Pricing\Events\PriceChanged;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Services\PriceScheduleService;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class CancelPricePeriodAction
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
            $locked = $this->schedule->lockAggregate($period->variant_price_id);
            $period = PricePeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            $cancelled = $this->schedule->cancelPeriod($period, $actorId);

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PricePeriodCancelled,
                subjectType: 'price_period',
                subjectId: (string) $cancelled->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['status' => $cancelled->status->value],
                metadata: null,
            ));

            DB::afterCommit(function () use ($cancelled, $locked): void {
                $this->cache->bumpGlobal();
                event(new PriceCancelled($cancelled->id, $cancelled->variant_price_id));
                event(new PriceChanged($locked->id, $locked->price_list_id, $locked->product_variant_id));
            });

            return $cancelled;
        });
    }
}
