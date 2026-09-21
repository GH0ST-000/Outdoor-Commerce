<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\PriceLists;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\DTOs\PriceListWriteData;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Enums\PricePeriodStatus;
use App\Domains\Pricing\Exceptions\PricingStateConflictException;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Models\PricePeriod;
use App\Domains\Pricing\Services\CurrencyCatalog;
use App\Domains\Pricing\Services\PricingCache;
use App\Domains\Pricing\Support\PriceListCodeNormalizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class UpdatePriceListAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CurrencyCatalog $currencies,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PriceList $priceList,
        PriceListWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PriceList {
        if (! $this->currencies->isEnabled($data->currencyCode)) {
            throw new PricingStateConflictException('Currency is not enabled.', 'CURRENCY_NOT_ENABLED');
        }

        $hasPublishedHistory = PricePeriod::query()
            ->whereHas('variantPrice', fn ($q) => $q->where('price_list_id', $priceList->id))
            ->where('status', PricePeriodStatus::Published)
            ->exists();

        if ($hasPublishedHistory && strtoupper($data->currencyCode) !== strtoupper($priceList->currency_code)) {
            throw new PricingStateConflictException(
                'Currency cannot change after published price history exists.',
                'PRICE_LIST_CURRENCY_LOCKED',
            );
        }

        $actorId = (int) $actor->getAuthIdentifier();
        $old = $priceList->only(['code', 'name', 'currency_code', 'status', 'is_default', 'priority', 'prices_include_tax']);

        return DB::transaction(function () use ($priceList, $data, $actorId, $old, $requestId, $ipAddress, $userAgent): PriceList {
            $priceList->fill([
                'code' => PriceListCodeNormalizer::normalize($data->code),
                'name' => $data->name,
                'currency_code' => strtoupper($data->currencyCode),
                'status' => $data->status,
                'priority' => $data->priority,
                'prices_include_tax' => $data->pricesIncludeTax,
                'updated_by' => $actorId,
            ]);
            $priceList->save();

            if ($data->isDefault && ! $priceList->is_default && $data->status === PriceListStatus::Active) {
                PriceList::query()
                    ->where('currency_code', $priceList->currency_code)
                    ->where('is_default', true)
                    ->whereKeyNot($priceList->id)
                    ->update(['is_default' => false]);
                $priceList->is_default = true;
                $priceList->save();
            }

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PriceListUpdated,
                subjectType: 'price_list',
                subjectId: (string) $priceList->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: $old,
                newValues: $priceList->only(['code', 'name', 'currency_code', 'status', 'is_default', 'priority', 'prices_include_tax']),
                metadata: null,
            ));

            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $priceList->fresh() ?? $priceList;
        });
    }
}
