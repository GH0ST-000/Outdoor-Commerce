<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Services;

use App\Domains\Checkout\Contracts\FulfillmentQuoteProvider;
use App\Domains\Checkout\DTOs\FulfillmentQuoteResultData;
use App\Domains\Checkout\Enums\FulfillmentMethodType;
use App\Domains\Checkout\Exceptions\CheckoutException;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Checkout\Models\FulfillmentMethod;
use App\Domains\Checkout\Models\PickupLocation;
use App\Domains\Checkout\Support\CheckoutLogger;

final class CheckoutFulfillmentService
{
    public function __construct(
        private readonly FulfillmentQuoteProvider $quotes,
        private readonly CheckoutLogger $logger,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function eligibleMethods(CheckoutSession $session, int $itemsSubtotalMinor, string $locale): array
    {
        $methods = FulfillmentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($methods as $method) {
            $pickup = $method->type === FulfillmentMethodType::StorePickup
                ? $this->defaultPickup()
                : null;
            $result = $this->quotes->quote(
                $method,
                $session->currency,
                $itemsSubtotalMinor,
                $session->shippingAddress,
                $method->type === FulfillmentMethodType::StorePickup ? $pickup : null,
            );

            $out[] = $this->presentMethod($method, $result, $locale, $pickup);
        }

        return $out;
    }

    public function quoteSelected(CheckoutSession $session, int $itemsSubtotalMinor): FulfillmentQuoteResultData
    {
        $method = $session->fulfillmentMethod;
        if ($method === null || ! $method->is_active) {
            throw CheckoutException::fulfillmentRequired();
        }

        $pickup = $session->pickupLocation;
        $result = $this->quotes->quote(
            $method,
            $session->currency,
            $itemsSubtotalMinor,
            $session->shippingAddress,
            $pickup,
        );

        if (! $result->eligible) {
            $this->logger->info('fulfillment_unavailable', [
                'session_public_id' => $session->public_id,
                'method_code' => $method->code,
            ]);

            if (($result->metadata['reason'] ?? null) === 'no_matching_rate') {
                throw CheckoutException::deliveryZoneUnavailable();
            }

            throw CheckoutException::fulfillmentUnavailable($result->unavailableReason);
        }

        return $result;
    }

    public function requireMethod(string $code): FulfillmentMethod
    {
        $method = FulfillmentMethod::query()->where('code', $code)->first();
        if ($method === null || ! $method->is_active) {
            throw CheckoutException::fulfillmentUnavailable();
        }

        return $method;
    }

    public function requirePickup(?string $publicId): ?PickupLocation
    {
        if ($publicId === null || $publicId === '') {
            return $this->defaultPickup();
        }

        $location = PickupLocation::query()->where('public_id', $publicId)->first();
        if ($location === null || ! $location->is_active) {
            throw CheckoutException::fulfillmentUnavailable('The pickup location is not available.');
        }

        return $location;
    }

    public function defaultPickup(): ?PickupLocation
    {
        return PickupLocation::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    public function methodStillEligible(CheckoutSession $session, int $itemsSubtotalMinor): bool
    {
        if ($session->fulfillment_method_id === null) {
            return false;
        }

        try {
            $this->quoteSelected($session, $itemsSubtotalMinor);

            return true;
        } catch (CheckoutException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function presentMethod(
        FulfillmentMethod $method,
        FulfillmentQuoteResultData $result,
        string $locale,
        ?PickupLocation $pickup = null,
    ): array {
        $payload = [
            'id' => $method->public_id,
            'code' => $method->code,
            'type' => $method->type->value,
            'name' => $method->localizedName($locale),
            'description' => $method->localizedDescription($locale),
            'eligible' => $result->eligible,
            'amount_minor' => $result->amountMinor,
            'currency' => $result->currency,
            'estimated_min_days' => $result->estimatedMinDays,
            'estimated_max_days' => $result->estimatedMaxDays,
            'unavailable_reason' => $result->unavailableReason,
            'requires_address' => $method->requires_address,
        ];

        if ($method->type === FulfillmentMethodType::StorePickup) {
            $locations = PickupLocation::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (PickupLocation $location): array => [
                    'id' => $location->public_id,
                    'name' => $location->localizedName($locale),
                    'address' => $location->localizedAddress($locale),
                    'phone' => $location->phone,
                    'working_hours' => $location->working_hours_translations[$locale]
                        ?? $location->working_hours_translations['ka']
                        ?? $location->working_hours_translations['en']
                        ?? null,
                    'instructions' => $location->instructions_translations[$locale]
                        ?? $location->instructions_translations['ka']
                        ?? $location->instructions_translations['en']
                        ?? null,
                    'selected' => $pickup !== null && $pickup->id === $location->id,
                ])
                ->all();
            $payload['pickup_locations'] = $locations;
        }

        return $payload;
    }
}
