<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Promotions;

use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\DTOs\PromotionWriteData;
use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;
use App\Domains\Pricing\Exceptions\PromotionCurrencyMismatchException;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Services\CurrencyCatalog;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreatePromotionAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CurrencyCatalog $currencies,
        private readonly PricingCache $cache,
    ) {}

    public function execute(
        PromotionWriteData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Promotion {
        $this->assertWriteData($data);
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($data, $actorId, $requestId, $ipAddress, $userAgent): Promotion {
            $promotion = Promotion::query()->create([
                'code' => Str::lower(trim($data->code)),
                'name' => $data->name,
                'description' => $data->description,
                'status' => PromotionStatus::Draft,
                'discount_type' => $data->discountType,
                'percentage_basis_points' => $data->percentageBasisPoints,
                'fixed_amount_minor' => $data->fixedAmountMinor,
                'currency_code' => $data->currencyCode !== null ? strtoupper($data->currencyCode) : null,
                'priority' => $data->priority,
                'stacking_mode' => $data->stackingMode,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'maximum_discount_minor' => $data->maximumDiscountMinor,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->recordAuditEvent->execute(new AuditEventData(
                actorUserId: $actorId,
                event: AuditEvent::PromotionCreated,
                subjectType: 'promotion',
                subjectId: (string) $promotion->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: null,
                newValues: ['code' => $promotion->code],
                metadata: null,
            ));

            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $promotion;
        });
    }

    public function assertWriteData(PromotionWriteData $data): void
    {
        if ($data->endsAt !== null && ! $data->endsAt->greaterThan($data->startsAt)) {
            throw new InvalidMoneyAmountException('Promotion end must be after start.');
        }

        if ($data->discountType === DiscountType::Percentage) {
            if ($data->percentageBasisPoints === null || $data->percentageBasisPoints < 1 || $data->percentageBasisPoints > 10000) {
                throw new InvalidMoneyAmountException('Percentage basis points must be between 1 and 10000.');
            }
        }

        if ($data->discountType === DiscountType::FixedAmount) {
            if ($data->fixedAmountMinor === null || $data->fixedAmountMinor < 1) {
                throw new InvalidMoneyAmountException('Fixed discount amount must be at least 1 minor unit.');
            }
            if ($data->currencyCode === null || ! $this->currencies->isEnabled($data->currencyCode)) {
                throw new PromotionCurrencyMismatchException('Fixed promotions require an enabled currency.');
            }
        }
    }
}
