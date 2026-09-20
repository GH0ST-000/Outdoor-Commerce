<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Catalog\DTOs\CatalogSellableRefData;
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

    public function isEligible(Promotion $promotion, CatalogSellableRefData $sellable, string $currencyCode): bool
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

        if ($this->matchesExclusion($targets, $sellable)) {
            return false;
        }

        return $this->matchesInclusion($targets, $sellable);
    }

    /**
     * @param  Collection<int, PromotionTarget>  $targets
     */
    private function matchesExclusion(Collection $targets, CatalogSellableRefData $sellable): bool
    {
        foreach ($targets->where('mode', PromotionTargetMode::Exclude) as $target) {
            if ($this->targetMatches($target, $sellable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, PromotionTarget>  $targets
     */
    private function matchesInclusion(Collection $targets, CatalogSellableRefData $sellable): bool
    {
        $includes = $targets->where('mode', PromotionTargetMode::Include);

        if ($includes->contains(fn (PromotionTarget $t): bool => $t->target_type === PromotionTargetType::AllProducts)) {
            return true;
        }

        foreach ($includes as $target) {
            if ($this->targetMatches($target, $sellable)) {
                return true;
            }
        }

        return false;
    }

    private function targetMatches(PromotionTarget $target, CatalogSellableRefData $sellable): bool
    {
        return match ($target->target_type) {
            PromotionTargetType::AllProducts => true,
            PromotionTargetType::Product => $target->target_id === $sellable->productId && ! $sellable->productDeleted,
            PromotionTargetType::ProductVariant => $target->target_id === $sellable->variantId && ! $sellable->variantDeleted,
            PromotionTargetType::Category => in_array($target->target_id, $sellable->categoryIds, true),
            PromotionTargetType::Brand => $sellable->brandId !== null && $sellable->brandId === $target->target_id
                && ! $sellable->brandDeleted,
        };
    }
}
