<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\PriceLists;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\Enums\PriceListStatus;
use App\Domains\Pricing\Events\PriceListActivated;
use App\Domains\Pricing\Exceptions\PricingStateConflictException;
use App\Domains\Pricing\Models\PriceList;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class ChangePriceListStatusAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PriceList $priceList,
        PriceListStatus $status,
        ?int $replacementDefaultPriceListId,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PriceList {
        $actorId = (int) $actor->getAuthIdentifier();
        $oldStatus = $priceList->status;

        return DB::transaction(function () use ($priceList, $status, $replacementDefaultPriceListId, $actorId, $oldStatus, $requestId, $ipAddress, $userAgent): PriceList {
            $priceList = PriceList::query()->whereKey($priceList->id)->lockForUpdate()->firstOrFail();

            if ($status === PriceListStatus::Archived && $priceList->is_default) {
                if ($replacementDefaultPriceListId === null) {
                    throw new PricingStateConflictException(
                        'Default price list cannot be archived without selecting a replacement.',
                        'DEFAULT_PRICE_LIST_REPLACEMENT_REQUIRED',
                    );
                }

                $replacement = PriceList::query()
                    ->whereKey($replacementDefaultPriceListId)
                    ->where('currency_code', $priceList->currency_code)
                    ->where('status', PriceListStatus::Active->value)
                    ->lockForUpdate()
                    ->firstOrFail();

                PriceList::query()
                    ->where('currency_code', $priceList->currency_code)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
                $replacement->is_default = true;
                $replacement->save();
                $priceList->is_default = false;
            }

            $priceList->status = $status;
            $priceList->updated_by = $actorId;
            $priceList->save();

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PriceListStatusChanged,
                subjectType: 'price_list',
                subjectId: (string) $priceList->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $status->value],
                metadata: null,
            ));

            DB::afterCommit(function () use ($priceList, $status): void {
                $this->cache->bumpGlobal();
                if ($status === PriceListStatus::Active) {
                    event(new PriceListActivated($priceList->id));
                }
            });

            return $priceList;
        });
    }
}
