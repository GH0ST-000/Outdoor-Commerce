<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Prices;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\DTOs\BulkUpsertPricesData;
use App\Domains\Pricing\DTOs\BulkUpsertPricesResult;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Events\PriceChanged;
use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;
use App\Domains\Pricing\Exceptions\PricingStateConflictException;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Models\VariantPrice;
use App\Domains\Pricing\Services\PriceScheduleService;
use App\Domains\Pricing\Services\PricingCache;
use App\Domains\Shared\Support\Clock;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class BulkUpsertPricesAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PriceScheduleService $schedule,
        private readonly PricingCache $cache,
        private readonly Clock $clock,
    ) {}

    public function execute(
        BulkUpsertPricesData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): BulkUpsertPricesResult {
        $max = (int) config('pricing.bulk.max_rows', 100);
        if (count($data->items) === 0) {
            throw new InvalidMoneyAmountException('Bulk price payload requires at least one item.');
        }
        if (count($data->items) > $max) {
            throw new PricingStateConflictException(
                "Bulk price payload exceeds maximum of {$max} rows.",
                'BULK_PRICE_LIMIT_EXCEEDED',
            );
        }

        $actorId = (int) $actor->getAuthIdentifier();
        $sorted = $data->items;
        usort(
            $sorted,
            static fn ($a, $b): int => [$a->priceListId, $a->productVariantId] <=> [$b->priceListId, $b->productVariantId],
        );

        return DB::transaction(function () use ($sorted, $data, $actorId, $requestId, $ipAddress, $userAgent): BulkUpsertPricesResult {
            $created = 0;
            $updated = 0;
            $events = [];

            foreach ($sorted as $item) {
                if ($item->amountMinor < 0) {
                    throw new InvalidMoneyAmountException('Price amount cannot be negative.');
                }

                $list = PriceList::query()->whereKey($item->priceListId)->lockForUpdate()->firstOrFail();
                $aggregate = VariantPrice::query()->firstOrCreate(
                    [
                        'price_list_id' => $list->id,
                        'product_variant_id' => $item->productVariantId,
                    ],
                    ['version' => 0],
                );
                $wasNew = $aggregate->wasRecentlyCreated;
                $locked = $this->schedule->lockAggregate($aggregate->id);
                $this->schedule->assertExpectedVersion($locked, $item->expectedVersion);

                $startsAt = $item->startsAt ?? $this->clock->now();

                if ($data->publish) {
                    $this->schedule->replaceEffective(
                        $locked,
                        $item->amountMinor,
                        $startsAt,
                        null,
                        $actorId,
                        $item->expectedVersion,
                        true,
                    );
                } else {
                    PricePeriod::query()->create([
                        'variant_price_id' => $locked->id,
                        'amount_minor' => $item->amountMinor,
                        'status' => PricePeriodStatus::Draft,
                        'starts_at' => $startsAt,
                        'ends_at' => null,
                        'created_by' => $actorId,
                    ]);
                }

                if ($wasNew) {
                    $created++;
                } else {
                    $updated++;
                }

                $events[] = [$locked->id, $locked->price_list_id, $locked->product_variant_id];
            }

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PricePeriodUpdated,
                subjectType: 'bulk_prices',
                subjectId: (string) count($sorted),
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['created' => $created, 'updated' => $updated, 'publish' => $data->publish],
                metadata: null,
            ));

            DB::afterCommit(function () use ($events): void {
                $this->cache->bumpGlobal();
                foreach ($events as [$variantPriceId, $priceListId, $variantId]) {
                    event(new PriceChanged($variantPriceId, $priceListId, $variantId));
                }
            });

            return new BulkUpsertPricesResult($created, $updated);
        });
    }
}
