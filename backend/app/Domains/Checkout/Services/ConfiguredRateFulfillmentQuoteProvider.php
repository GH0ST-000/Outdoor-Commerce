<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Services;

use App\Domains\Checkout\Contracts\FulfillmentQuoteProvider;
use App\Domains\Checkout\DTOs\FulfillmentQuoteResultData;
use App\Domains\Checkout\Enums\FulfillmentMethodType;
use App\Domains\Checkout\Models\CheckoutAddress;
use App\Domains\Checkout\Models\DeliveryRateRule;
use App\Domains\Checkout\Models\FulfillmentMethod;
use App\Domains\Checkout\Models\PickupLocation;
use App\Domains\Shared\Support\Clock;

final class ConfiguredRateFulfillmentQuoteProvider implements FulfillmentQuoteProvider
{
    public function __construct(private readonly Clock $clock) {}

    public function quote(
        FulfillmentMethod $method,
        string $currency,
        int $itemsSubtotalMinor,
        ?CheckoutAddress $shippingAddress,
        ?PickupLocation $pickupLocation,
    ): FulfillmentQuoteResultData {
        if (! $method->is_active) {
            return $this->unavailable('This fulfillment method is not available.');
        }

        if (strtoupper($method->currency) !== strtoupper($currency)) {
            return $this->unavailable('This fulfillment method does not match the checkout currency.', $method->currency);
        }

        if ($method->type === FulfillmentMethodType::StorePickup) {
            return $this->quotePickup($method, $currency, $itemsSubtotalMinor, $pickupLocation);
        }

        if ($shippingAddress === null) {
            return $this->unavailable('A delivery address is required.');
        }

        return $this->quoteDelivery($method, $currency, $itemsSubtotalMinor, $shippingAddress);
    }

    private function quotePickup(
        FulfillmentMethod $method,
        string $currency,
        int $itemsSubtotalMinor,
        ?PickupLocation $pickupLocation,
    ): FulfillmentQuoteResultData {
        if ($pickupLocation === null || ! $pickupLocation->is_active) {
            return $this->unavailable('Select an available pickup location.');
        }

        $amount = $this->amountFromMethod($method, $itemsSubtotalMinor);

        return new FulfillmentQuoteResultData(
            eligible: true,
            amountMinor: $amount,
            currency: $currency,
            estimatedMinDays: $method->estimated_min_days,
            estimatedMaxDays: $method->estimated_max_days,
            unavailableReason: null,
            metadata: [
                'pickup_location_public_id' => $pickupLocation->public_id,
                'rule' => 'pickup_base_or_free_above',
            ],
        );
    }

    private function quoteDelivery(
        FulfillmentMethod $method,
        string $currency,
        int $itemsSubtotalMinor,
        CheckoutAddress $address,
    ): FulfillmentQuoteResultData {
        $now = $this->clock->now();
        $method->loadMissing('rateRules.zone');

        $matches = [];
        foreach ($method->rateRules as $rule) {
            if (! $this->ruleIsLive($rule, $now)) {
                continue;
            }

            $zone = $rule->zone;
            if ($zone === null || ! $zone->is_active) {
                continue;
            }

            if (! $this->zoneMatches($zone->country_code, $zone->region, $zone->municipality_or_city, $zone->postal_code_pattern, $address)) {
                continue;
            }

            if ($rule->minimum_subtotal_minor !== null && $itemsSubtotalMinor < $rule->minimum_subtotal_minor) {
                continue;
            }

            if ($rule->maximum_subtotal_minor !== null && $itemsSubtotalMinor > $rule->maximum_subtotal_minor) {
                continue;
            }

            $matches[] = $rule;
        }

        if ($matches === []) {
            return new FulfillmentQuoteResultData(
                eligible: false,
                amountMinor: null,
                currency: $currency,
                estimatedMinDays: null,
                estimatedMaxDays: null,
                unavailableReason: 'Delivery is not available for this address.',
                metadata: ['reason' => 'no_matching_rate'],
            );
        }

        usort(
            $matches,
            static fn (DeliveryRateRule $a, DeliveryRateRule $b): int => $a->priority <=> $b->priority,
        );

        $best = $matches[0];
        $tied = array_filter(
            $matches,
            static fn (DeliveryRateRule $rule): bool => $rule->priority === $best->priority && $rule->id !== $best->id,
        );

        if ($tied !== []) {
            return new FulfillmentQuoteResultData(
                eligible: false,
                amountMinor: null,
                currency: $currency,
                estimatedMinDays: null,
                estimatedMaxDays: null,
                unavailableReason: 'Delivery is not available for this address.',
                metadata: ['reason' => 'ambiguous_rate_priority'],
            );
        }

        $amount = $best->price_minor;
        $freeAbove = $best->free_above_minor ?? $method->free_above_minor;
        if ($freeAbove !== null && $itemsSubtotalMinor >= $freeAbove) {
            $amount = 0;
        }

        return new FulfillmentQuoteResultData(
            eligible: true,
            amountMinor: $amount,
            currency: $currency,
            estimatedMinDays: $method->estimated_min_days,
            estimatedMaxDays: $method->estimated_max_days,
            unavailableReason: null,
            metadata: [
                'rate_rule_id' => $best->id,
                'zone_code' => $best->zone?->code,
                'free_applied' => $amount === 0 && $best->price_minor > 0,
            ],
        );
    }

    private function amountFromMethod(FulfillmentMethod $method, int $itemsSubtotalMinor): int
    {
        if ($method->free_above_minor !== null && $itemsSubtotalMinor >= $method->free_above_minor) {
            return 0;
        }

        return $method->base_price_minor;
    }

    private function ruleIsLive(DeliveryRateRule $rule, \DateTimeInterface $now): bool
    {
        if (! $rule->is_active) {
            return false;
        }

        if ($rule->starts_at !== null && $rule->starts_at->greaterThan($now)) {
            return false;
        }

        if ($rule->ends_at !== null && $rule->ends_at->lessThan($now)) {
            return false;
        }

        return true;
    }

    private function zoneMatches(
        string $countryCode,
        ?string $region,
        ?string $city,
        ?string $postalPattern,
        CheckoutAddress $address,
    ): bool {
        if (strtoupper($countryCode) !== strtoupper($address->country_code)) {
            return false;
        }

        if (is_string($region) && $region !== '' && ! $this->samePlace($region, $address->region)) {
            return false;
        }

        if (is_string($city) && $city !== '' && ! $this->samePlace($city, $address->municipality_or_city)) {
            return false;
        }

        if (is_string($postalPattern) && $postalPattern !== '') {
            $postal = (string) $address->postal_code;
            if ($postal === '' || preg_match('/'.$postalPattern.'/u', $postal) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function samePlace(string $expected, ?string $actual): bool
    {
        return mb_strtolower(trim($expected)) === mb_strtolower(trim((string) $actual));
    }

    private function unavailable(string $reason, string $currency = 'GEL'): FulfillmentQuoteResultData
    {
        return new FulfillmentQuoteResultData(
            eligible: false,
            amountMinor: null,
            currency: $currency,
            estimatedMinDays: null,
            estimatedMaxDays: null,
            unavailableReason: $reason,
        );
    }
}
