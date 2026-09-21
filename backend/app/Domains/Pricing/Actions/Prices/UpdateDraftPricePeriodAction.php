<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Prices;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\DTOs\PricePeriodWriteData;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;
use App\Domains\Pricing\Exceptions\PricePeriodImmutableException;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class UpdateDraftPricePeriodAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PricePeriod $period,
        PricePeriodWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PricePeriod {
        if ($period->status !== PricePeriodStatus::Draft) {
            throw new PricePeriodImmutableException('Only draft periods can be updated.');
        }

        if ($data->amountMinor < 0) {
            throw new InvalidMoneyAmountException('Price amount cannot be negative.');
        }

        if ($data->endsAt !== null && ! $data->endsAt->greaterThan($data->startsAt)) {
            throw new InvalidMoneyAmountException('Price period end must be after start.');
        }

        $actorId = (int) $actor->getAuthIdentifier();
        $old = $period->only(['amount_minor', 'starts_at', 'ends_at']);

        return DB::transaction(function () use ($period, $data, $actorId, $old, $requestId, $ipAddress, $userAgent): PricePeriod {
            $period->update([
                'amount_minor' => $data->amountMinor,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
            ]);

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PricePeriodUpdated,
                subjectType: 'price_period',
                subjectId: (string) $period->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: $old,
                newValues: $period->only(['amount_minor', 'starts_at', 'ends_at']),
                metadata: null,
            ));

            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $period->fresh() ?? $period;
        });
    }
}
