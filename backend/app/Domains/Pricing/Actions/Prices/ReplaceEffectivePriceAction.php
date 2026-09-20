<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Prices;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\DTOs\ReplaceEffectivePriceData;
use App\Domains\Pricing\Events\PriceChanged;
use App\Domains\Pricing\Events\PricePublished;
use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Pricing\Services\PriceScheduleService;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class ReplaceEffectivePriceAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PriceScheduleService $schedule,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PriceList $priceList,
        int $variantId,
        ReplaceEffectivePriceData $data,
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
            $period = $this->schedule->replaceEffective(
                $aggregate,
                $data->amountMinor,
                $data->startsAt,
                $data->endsAt,
                $actorId,
                $data->expectedVersion,
                $data->closePreviousAtStart,
            );

            $fresh = $aggregate->fresh(['periods', 'priceList', 'productVariant']) ?? $aggregate;

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PricePeriodSuperseded,
                subjectType: 'variant_price',
                subjectId: (string) $fresh->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: [
                    'price_period_id' => $period->id,
                    'amount_minor' => $period->amount_minor,
                    'version' => $fresh->version,
                ],
                metadata: null,
            ));

            DB::afterCommit(function () use ($period, $fresh): void {
                $this->cache->bumpGlobal();
                event(new PricePublished($period->id, $period->variant_price_id));
                event(new PriceChanged($fresh->id, $fresh->price_list_id, $fresh->product_variant_id));
            });

            return $fresh;
        });
    }
}
