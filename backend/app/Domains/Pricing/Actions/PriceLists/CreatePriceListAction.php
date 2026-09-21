<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\PriceLists;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\DTOs\PriceListWriteData;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Services\CurrencyCatalog;
use App\Domains\Pricing\Services\PricingCache;
use App\Domains\Pricing\Support\PriceListCodeNormalizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class CreatePriceListAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CurrencyCatalog $currencies,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PriceListWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PriceList {
        if (! $this->currencies->isEnabled($data->currencyCode)) {
            throw new \InvalidArgumentException('Currency is not enabled.');
        }

        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($data, $actorId, $requestId, $ipAddress, $userAgent): PriceList {
            $hasDefault = PriceList::query()
                ->where('currency_code', strtoupper($data->currencyCode))
                ->where('is_default', true)
                ->whereNull('deleted_at')
                ->exists();

            $isDefault = $data->isDefault || (! $hasDefault && $data->status === PriceListStatus::Active);

            if ($isDefault) {
                PriceList::query()
                    ->where('currency_code', strtoupper($data->currencyCode))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $list = PriceList::query()->create([
                'code' => PriceListCodeNormalizer::normalize($data->code),
                'name' => $data->name,
                'currency_code' => strtoupper($data->currencyCode),
                'status' => $data->status,
                'is_default' => $isDefault,
                'priority' => $data->priority,
                'prices_include_tax' => $data->pricesIncludeTax,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PriceListCreated,
                subjectType: 'price_list',
                subjectId: (string) $list->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['code' => $list->code, 'currency' => $list->currency_code],
                metadata: null,
            ));

            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $list;
        });
    }
}
