<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Promotions;

use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Pricing\DTOs\PromotionTargetData;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Events\PromotionTargetsChanged;
use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Models\PromotionTarget;
use App\Domains\Pricing\Services\PricingCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final class SyncPromotionTargetsAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PricingCache $cache,
    ) {}

    /**
     * @param  list<PromotionTargetData>  $targets
     */
    public function execute(
        Promotion $promotion,
        array $targets,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Promotion {
        $actorId = (int) $actor->getAuthIdentifier();

        $synced = DB::transaction(function () use ($promotion, $targets, $actorId): Promotion {
            $seen = [];
            foreach ($targets as $target) {
                $this->assertTarget($target);
                $key = $target->targetType->value.'|'.($target->targetId ?? 'null').'|'.$target->mode->value;
                if (isset($seen[$key])) {
                    throw new InvalidMoneyAmountException('Duplicate promotion targets are not allowed.');
                }
                $seen[$key] = true;
            }

            $promotion->targets()->delete();

            foreach ($targets as $target) {
                PromotionTarget::query()->create([
                    'promotion_id' => $promotion->id,
                    'target_type' => $target->targetType,
                    'target_id' => $target->targetId,
                    'mode' => $target->mode,
                ]);
            }

            $promotion->updated_by = $actorId;
            $promotion->save();

            return $promotion->fresh(['targets']) ?? $promotion;
        });

        $this->recordAuditEvent->execute(new AuditEventData(
            actorUserId: $actorId,
            event: AuditEvent::PromotionTargetsUpdated,
            subjectType: 'promotion',
            subjectId: (string) $synced->id,
            requestId: $requestId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            oldValues: null,
            newValues: [
                'target_count' => count($targets),
                'summary' => collect($targets)->map(fn (PromotionTargetData $t): string => $t->targetType->value.':'.($t->targetId ?? '*').':'.$t->mode->value)->all(),
            ],
            metadata: null,
        ));

        $this->cache->bumpGlobal();

        DB::afterCommit(fn () => event(new PromotionTargetsChanged(promotionId: $synced->id)));

        return $synced;
    }

    private function assertTarget(PromotionTargetData $target): void
    {
        if ($target->targetType === PromotionTargetType::AllProducts) {
            if ($target->targetId !== null) {
                throw new InvalidMoneyAmountException('all_products target must not have a target_id.');
            }
            if ($target->mode !== PromotionTargetMode::Include) {
                throw new InvalidMoneyAmountException('all_products must be an inclusion.');
            }

            return;
        }

        if ($target->targetId === null) {
            throw new InvalidMoneyAmountException('Target id is required for '.$target->targetType->value);
        }

        $exists = match ($target->targetType) {
            PromotionTargetType::Product => Product::query()->whereKey($target->targetId)->exists(),
            PromotionTargetType::ProductVariant => ProductVariant::query()->whereKey($target->targetId)->exists(),
            PromotionTargetType::Category => Category::query()->whereKey($target->targetId)->exists(),
            PromotionTargetType::Brand => Brand::query()->whereKey($target->targetId)->exists(),
        };

        if (! $exists) {
            throw new InvalidMoneyAmountException('Promotion target does not exist: '.$target->targetType->value);
        }
    }
}
