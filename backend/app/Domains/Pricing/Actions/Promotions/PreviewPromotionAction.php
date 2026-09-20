<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Actions\Promotions;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\DTOs\PreviewPromotionData;
use App\Domains\Pricing\DTOs\PromotionTargetData;
use App\Domains\Pricing\DTOs\PromotionWriteData;
use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Models\PromotionTarget;
use App\Domains\Pricing\Services\EffectiveBasePriceResolver;
use App\Domains\Pricing\Services\PromotionCalculator;
use App\Domains\Pricing\Services\PromotionEligibilityService;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Collection;

final class PreviewPromotionAction
{
    public function __construct(
        private readonly EffectiveBasePriceResolver $basePrices,
        private readonly PromotionEligibilityService $eligibility,
        private readonly PromotionCalculator $calculator,
        private readonly Clock $clock,
    ) {}

    /**
     * @return array{samples: list<array<string, mixed>>, warnings: list<array{code: string, message: string}>, conflicts: list<array{code: string, message: string}>}
     */
    public function execute(PreviewPromotionData $data): array
    {
        $promotion = $this->resolvePromotion($data);
        $targets = $this->resolveTargets($data, $promotion);
        $promotion->setRelation('targets', $targets);

        $variantIds = $data->variantIds;
        if ($variantIds === []) {
            $variantIds = ProductVariant::query()->active()->limit(5)->pluck('id')->all();
        }

        $samples = [];
        $warnings = [];
        $conflicts = [];

        if ($targets->where('mode', PromotionTargetMode::Include)->isEmpty()) {
            $warnings[] = ['code' => 'MISSING_INCLUSION', 'message' => 'Promotion has no inclusion targets.'];
        }

        foreach ($variantIds as $variantId) {
            $variant = ProductVariant::query()->with('product')->find($variantId);
            if ($variant === null) {
                $samples[] = [
                    'variant_id' => $variantId,
                    'eligible' => false,
                    'reason' => 'Variant not found.',
                ];

                continue;
            }

            try {
                $base = $this->basePrices->resolveForVariant($variant->id, $data->priceListId);
            } catch (\Throwable $e) {
                $samples[] = [
                    'variant_id' => $variantId,
                    'eligible' => false,
                    'reason' => $e->getMessage(),
                    'base_amount_minor' => null,
                    'final_amount_minor' => null,
                    'discount_amount_minor' => null,
                ];

                continue;
            }

            $eligible = $this->eligibility->isEligible($promotion, $variant, $variant->product, $base->amount->currencyCode);
            if (! $eligible) {
                $samples[] = [
                    'variant_id' => $variantId,
                    'eligible' => false,
                    'reason' => 'Not eligible for this promotion.',
                    'base_amount_minor' => $base->amount->amountMinor,
                    'final_amount_minor' => $base->amount->amountMinor,
                    'discount_amount_minor' => 0,
                ];

                continue;
            }

            $calc = $this->calculator->calculate($base->amount, collect([$promotion]));
            $samples[] = [
                'variant_id' => $variantId,
                'eligible' => true,
                'reason' => null,
                'base_amount_minor' => $base->amount->amountMinor,
                'final_amount_minor' => $calc['final']->amountMinor,
                'discount_amount_minor' => max(0, $base->amount->amountMinor - $calc['final']->amountMinor),
                'quote' => [
                    'variant_id' => $variantId,
                    'price_list_id' => $base->priceListId,
                    'currency' => $base->amount->currencyCode,
                    'base_amount_minor' => $base->amount->amountMinor,
                    'final_amount_minor' => $calc['final']->amountMinor,
                    'discount_amount_minor' => max(0, $base->amount->amountMinor - $calc['final']->amountMinor),
                    'applied_promotions' => $calc['applied'],
                    'calculated_at' => $this->clock->now()->toIso8601String(),
                    'price_period_id' => $base->pricePeriodId,
                    'pricing_signature' => null,
                ],
            ];
        }

        return [
            'samples' => $samples,
            'warnings' => $warnings,
            'conflicts' => $conflicts,
        ];
    }

    private function resolvePromotion(PreviewPromotionData $data): Promotion
    {
        if ($data->existingPromotionId !== null) {
            return Promotion::query()->with('targets')->findOrFail($data->existingPromotionId);
        }

        $write = $data->promotion ?? new PromotionWriteData(
            code: 'preview',
            name: 'Preview',
            description: null,
            discountType: DiscountType::Percentage,
            percentageBasisPoints: 1000,
            fixedAmountMinor: null,
            currencyCode: 'GEL',
            priority: 10,
            stackingMode: PromotionStackingMode::Exclusive,
            startsAt: $this->clock->now()->subHour(),
            endsAt: null,
            maximumDiscountMinor: null,
        );

        $promotion = new Promotion([
            'code' => $write->code,
            'name' => $write->name,
            'description' => $write->description,
            'status' => PromotionStatus::Active,
            'discount_type' => $write->discountType,
            'percentage_basis_points' => $write->percentageBasisPoints,
            'fixed_amount_minor' => $write->fixedAmountMinor,
            'currency_code' => $write->currencyCode !== null ? strtoupper($write->currencyCode) : null,
            'priority' => $write->priority,
            'stacking_mode' => $write->stackingMode,
            'starts_at' => $write->startsAt,
            'ends_at' => $write->endsAt,
            'maximum_discount_minor' => $write->maximumDiscountMinor,
        ]);
        $promotion->id = 0;

        return $promotion;
    }

    /**
     * @return Collection<int, PromotionTarget>
     */
    private function resolveTargets(PreviewPromotionData $data, Promotion $promotion): Collection
    {
        if ($data->targets !== null) {
            return collect($data->targets)->map(function (PromotionTargetData $target): PromotionTarget {
                return new PromotionTarget([
                    'target_type' => $target->targetType,
                    'target_id' => $target->targetId,
                    'mode' => $target->mode,
                ]);
            });
        }

        if ($promotion->relationLoaded('targets') && $promotion->targets->isNotEmpty()) {
            return $promotion->targets;
        }

        return collect([
            new PromotionTarget([
                'target_type' => PromotionTargetType::AllProducts,
                'target_id' => null,
                'mode' => PromotionTargetMode::Include,
            ]),
        ]);
    }
}
