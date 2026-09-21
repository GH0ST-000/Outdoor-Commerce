<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Prices;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\DTOs\PricePeriodWriteData;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Pricing\Services\PriceScheduleService;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class CreateDraftPricePeriodAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PriceScheduleService $schedule,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PriceList $priceList,
        int $variantId,
        PricePeriodWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): VariantPrice {
        if ($data->amountMinor < 0) {
            throw new InvalidMoneyAmountException('Price amount cannot be negative.');
        }

        if ($data->endsAt !== null && ! $data->endsAt->greaterThan($data->startsAt)) {
            throw new InvalidMoneyAmountException('Price period end must be after start.');
        }

        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($priceList, $variantId, $data, $actorId, $requestId, $ipAddress, $userAgent): VariantPrice {
            $aggregate = $this->schedule->findOrCreateAggregate($priceList, $variantId);
            $locked = $this->schedule->lockAggregate($aggregate->id);
            $this->schedule->assertExpectedVersion($locked, $data->expectedVersion);

            $period = PricePeriod::query()->create([
                'variant_price_id' => $locked->id,
                'amount_minor' => $data->amountMinor,
                'status' => PricePeriodStatus::Draft,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'created_by' => $actorId,
            ]);

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PricePeriodCreated,
                subjectType: 'price_period',
                subjectId: (string) $period->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: [
                    'variant_price_id' => $locked->id,
                    'amount_minor' => $period->amount_minor,
                    'starts_at' => $period->starts_at?->toIso8601String(),
                ],
                metadata: null,
            ));

            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $locked->fresh(['periods', 'priceList', 'productVariant']) ?? $locked;
        });
    }
}
