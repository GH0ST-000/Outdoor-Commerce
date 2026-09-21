<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\PriceLists;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Exceptions\PriceListInactiveException;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class SetDefaultPriceListAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PriceList $priceList,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PriceList {
        if ($priceList->status !== PriceListStatus::Active) {
            throw new PriceListInactiveException('Only an active price list can be set as default.');
        }

        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($priceList, $actorId, $requestId, $ipAddress, $userAgent): PriceList {
            PriceList::query()
                ->where('currency_code', $priceList->currency_code)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $priceList = PriceList::query()->whereKey($priceList->id)->lockForUpdate()->firstOrFail();
            $priceList->is_default = true;
            $priceList->status = PriceListStatus::Active;
            $priceList->updated_by = $actorId;
            $priceList->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PriceListDefaultChanged,
                subjectType: 'price_list',
                subjectId: (string) $priceList->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['is_default' => true],
                metadata: null,
            ));

            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $priceList;
        });
    }
}
