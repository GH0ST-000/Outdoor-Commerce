<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\PriceLists;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class RestorePriceListAction
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
        $priceList->restore();
        $priceList->status = PriceListStatus::Draft;
        $priceList->updated_by = (int) $actor->getAuthIdentifier();
        $priceList->save();

        $this->recordAuditEvent->execute(new AuditEventData(
            actorUserId: (int) $actor->getAuthIdentifier(),
            event: AuditEvent::PriceListRestored,
            subjectType: 'price_list',
            subjectId: (string) $priceList->id,
            requestId: $requestId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            oldValues: null,
            newValues: ['deleted_at' => null, 'status' => PriceListStatus::Draft->value],
            metadata: null,
        ));

        DB::afterCommit(fn () => $this->cache->bumpGlobal());

        return $priceList->fresh() ?? $priceList;
    }
}
