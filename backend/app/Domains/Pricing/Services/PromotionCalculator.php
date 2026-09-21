<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use App\Domains\Pricing\Enums\DiscountType;
use App\Domains\Pricing\Enums\PromotionStackingMode;
use App\Domains\Pricing\Models\Promotion;
use App\Domains\Pricing\ValueObjects\Money;
use Illuminate\Support\Collection;

final class PromotionCalculator
{
    /**
     * @param  Collection<int, Promotion>  $candidates
     * @return array{final: Money, applied: list<array<string, mixed>>}
     */
    public function calculate(Money $base, Collection $candidates): array
    {
        if ($candidates->isEmpty()) {
            return ['final' => $base, 'applied' => []];
        }

        $exclusive = $candidates->where('stacking_mode', PromotionStackingMode::Exclusive)->values();
        $combinable = $candidates->where('stacking_mode', PromotionStackingMode::Combinable)->values();

        $bestExclusive = $this->bestExclusive($base, $exclusive);
        $combined = $this->applyCombinable($base, $combinable);

        $candidatesFinal = [
            ['final' => $base, 'applied' => []],
            $bestExclusive,
            $combined,
        ];

        usort($candidatesFinal, function (array $a, array $b): int {
            $cmp = $a['final']->amountMinor <=> $b['final']->amountMinor;
            if ($cmp !== 0) {
                return $cmp;
            }

            return count($b['applied']) <=> count($a['applied']);
        });

        return $candidatesFinal[0];
    }

    /**
     * @param  Collection<int, Promotion>  $exclusive
     * @return array{final: Money, applied: list<array<string, mixed>>}
     */
    private function bestExclusive(Money $base, Collection $exclusive): array
    {
        $best = ['final' => $base, 'applied' => []];

        foreach ($exclusive as $promotion) {
            $result = $this->applySingle($base, $promotion);
            if ($result['final']->amountMinor < $best['final']->amountMinor) {
                $best = $result;
            } elseif ($result['final']->amountMinor === $best['final']->amountMinor) {
                $bestPriority = $best['applied'][0]['priority'] ?? -1;
                if ($promotion->priority > $bestPriority || (
                    $promotion->priority === $bestPriority && $promotion->id < ($best['applied'][0]['promotion_id'] ?? PHP_INT_MAX)
                )) {
                    $best = $result;
                }
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int, Promotion>  $combinable
     * @return array{final: Money, applied: list<array<string, mixed>>}
     */
    private function applyCombinable(Money $base, Collection $combinable): array
    {
        $max = (int) config('pricing.promotions.max_combinable', 3);
        $sorted = $combinable->sort(function (Promotion $a, Promotion $b): int {
            if ($a->priority !== $b->priority) {
                return $b->priority <=> $a->priority;
            }

            return $a->id <=> $b->id;
        })->values();

        $current = $base;
        /** @var list<array<string, mixed>> $applied */
        $applied = [];

        $percent = $sorted->filter(fn (Promotion $p): bool => $p->discount_type === DiscountType::Percentage);
        $fixed = $sorted->filter(fn (Promotion $p): bool => $p->discount_type === DiscountType::FixedAmount);
        $ordered = $percent->merge($fixed);

        foreach ($ordered as $promotion) {
            if (count($applied) >= $max) {
                break;
            }

            $before = $current;
            $single = $this->applySingle($current, $promotion);
            if ($single['final']->amountMinor === $before->amountMinor) {
                continue;
            }

            $current = $single['final'];
            $applied[] = $single['applied'][0];
        }

        return ['final' => $this->enforceMinimumFinal($current), 'applied' => $applied];
    }

    /**
     * @return array{final: Money, applied: list<array<string, mixed>>}
     */
    private function applySingle(Money $base, Promotion $promotion): array
    {
        $discountMinor = 0;

        if ($promotion->discount_type === DiscountType::Percentage) {
            $discount = $base->percentageDiscount(
                (int) $promotion->percentage_basis_points,
                $promotion->maximum_discount_minor !== null ? (int) $promotion->maximum_discount_minor : null,
            );
            $discountMinor = $discount->amountMinor;
            $final = $base->subtract($discount);
        } else {
            $final = $base->applyDiscount($base->fixedDiscount((int) $promotion->fixed_amount_minor));
            $discountMinor = $base->amountMinor - $final->amountMinor;
        }

        $final = $this->enforceMinimumFinal($final);

        return [
            'final' => $final,
            'applied' => [[
                'promotion_id' => $promotion->id,
                'code' => $promotion->code,
                'discount_type' => $promotion->discount_type->value,
                'discount_amount_minor' => $discountMinor,
                'priority' => $promotion->priority,
                'stacking_mode' => $promotion->stacking_mode->value,
            ]],
        ];
    }

    private function enforceMinimumFinal(Money $money): Money
    {
        $allowZero = (bool) config('pricing.promotions.allow_zero_final_price', true);
        $min = (int) config('pricing.promotions.min_final_amount_minor', 0);

        if ($allowZero && $min === 0) {
            return $money;
        }

        if ($money->amountMinor < $min) {
            return Money::of($min, $money->currencyCode);
        }

        return $money;
    }
}
