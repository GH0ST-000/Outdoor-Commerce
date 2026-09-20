<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Enums\PromotionStatus;
use App\Domains\Pricing\Enums\PromotionTargetMode;
use App\Domains\Pricing\Enums\PromotionTargetType;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\Models\PromotionTarget;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class PromotionEligibilityService
{
    public function __construct(
        private readonly Clock $clock,
    ) {}

    /**
     * @return Collection<int, Promotion>
     */
    public function effectivePromotions(string $currencyCode, ?CarbonImmutable $at = null): Collection
    {
        $moment = $at ?? $this->clock->now();

        return Promotion::query()
            ->with('targets')
            ->where('status', PromotionStatus::Active)
            ->where('starts_at', '<=', $moment)
            ->where(function ($query) use ($moment): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $moment);
            })
            ->where(function ($query) use ($currencyCode): void {
                $query->whereNull('currency_code')->orWhere('currency_code', strtoupper($currencyCode));
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    public function isEligible(Promotion $promotion, ProductVariant $variant, Product $product, string $currencyCode): bool
    {
        if ($promotion->status !== PromotionStatus::Active) {
            return false;
        }

        if ($promotion->discount_type->value === 'fixed_amount') {
            if ($promotion->currency_code === null || strtoupper($promotion->currency_code) !== strtoupper($currencyCode)) {
                return false;
            }
        }

        $targets = $promotion->targets;
        if ($targets->isEmpty()) {
            return false;
        }

        $product->loadMissing(['categories', 'brand']);

        if ($this->matchesExclusion($targets, $variant, $product)) {
            return false;
        }

        return $this->matchesInclusion($targets, $variant, $product);
    }

    /**
     * @param  Collection<int, PromotionTarget>  $targets
     */
    private function matchesExclusion(Collection $targets, ProductVariant $variant, Product $product): bool
    {
        foreach ($targets->where('mode', PromotionTargetMode::Exclude) as $target) {
            if ($this->targetMatches($target, $variant, $product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, PromotionTarget>  $targets
     */
    private function matchesInclusion(Collection $targets, ProductVariant $variant, Product $product): bool
    {
        $includes = $targets->where('mode', PromotionTargetMode::Include);

        if ($includes->contains(fn (PromotionTarget $t): bool => $t->target_type === PromotionTargetType::AllProducts)) {
            return true;
        }

        foreach ($includes as $target) {
            if ($this->targetMatches($target, $variant, $product)) {
                return true;
            }
        }

        return false;
    }

    private function targetMatches(PromotionTarget $target, ProductVariant $variant, Product $product): bool
    {
        return match ($target->target_type) {
            PromotionTargetType::AllProducts => true,
            PromotionTargetType::Product => $target->target_id === $product->id && ! $product->trashed(),
            PromotionTargetType::ProductVariant => $target->target_id === $variant->id && ! $variant->trashed(),
            PromotionTargetType::Category => $product->categories->contains('id', $target->target_id),
            PromotionTargetType::Brand => $product->brand_id !== null && $product->brand_id === $target->target_id
                && $product->brand !== null && ! $product->brand->trashed(),
        };
    }
}
