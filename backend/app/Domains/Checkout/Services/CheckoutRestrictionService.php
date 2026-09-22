<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Services;

use App\Domains\Checkout\Enums\CheckoutRestrictionOutcome;
use App\Domains\Checkout\Exceptions\CheckoutException;
use App\Domains\Checkout\Models\CheckoutRestrictionRule;

final class CheckoutRestrictionService
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, CheckoutRestrictionRule>
     */
    public function rulesFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return CheckoutRestrictionRule::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('product_id')
            ->all();
    }

    /**
     * @return array{code: string, outcome: string, message: string, required_action: string|null}|null
     */
    public function snapshot(?CheckoutRestrictionRule $rule, string $locale): ?array
    {
        if ($rule === null || $rule->outcome === CheckoutRestrictionOutcome::Allowed) {
            return null;
        }

        return [
            'code' => $rule->code,
            'outcome' => $rule->outcome->value,
            'message' => $rule->localizedMessage($locale),
            'required_action' => $rule->required_action,
        ];
    }

    public function assertNotBlocked(?CheckoutRestrictionRule $rule, string $locale, ?string $itemId = null): void
    {
        if ($rule === null || $rule->outcome !== CheckoutRestrictionOutcome::CheckoutBlocked) {
            return;
        }

        throw CheckoutException::restrictionBlocked([
            'item_id' => $itemId,
            'restriction' => $this->snapshot($rule, $locale),
        ]);
    }
}
