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

final class ArchivePriceListAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly ChangePriceListStatusAction $changeStatus,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PriceList $priceList,
        ?int $replacementDefaultPriceListId,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PriceList {
        return DB::transaction(function () use ($priceList, $replacementDefaultPriceListId, $actor, $requestId, $ipAddress, $userAgent): PriceList {
            $updated = $this->changeStatus->execute(
                $priceList,
                PriceListStatus::Archived,
                $replacementDefaultPriceListId,
                $actor,
                $requestId,
                $ipAddress,
                $userAgent,
            );

            $updated->delete();

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: (int) $actor->getAuthIdentifier(),
                event: AuditEvent::PriceListArchived,
                subjectType: 'price_list',
                subjectId: (string) $priceList->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['deleted_at' => now()->toIso8601String()],
                metadata: null,
            ));

            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $updated;
        });
    }
}
