<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

class ShipmentException extends DomainException implements ProvidesErrorDetails
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        string $errorCode,
        private readonly array $details = [],
    ) {
        parent::__construct($message, $errorCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function errorDetails(): array
    {
        return $this->details;
    }

    public static function orderNotEligible(): self
    {
        return new self('This order cannot enter fulfillment.', 'FULFILLMENT_ORDER_NOT_ELIGIBLE');
    }

    public static function orderNotPaid(): self
    {
        return new self('This order has not been paid.', 'FULFILLMENT_ORDER_NOT_PAID');
    }

    public static function nothingRemaining(): self
    {
        return new self('No remaining quantity can be fulfilled.', 'FULFILLMENT_NOTHING_REMAINING');
    }

    public static function quantityExceeded(): self
    {
        return new self('Requested quantity exceeds the remaining fulfillable amount.', 'FULFILLMENT_QUANTITY_EXCEEDED');
    }

    public static function warehouseInvalid(): self
    {
        return new self('The selected warehouse cannot fulfill this shipment.', 'FULFILLMENT_WAREHOUSE_INVALID');
    }

    public static function typeInvalid(): self
    {
        return new self('This fulfillment type is not valid for the order.', 'FULFILLMENT_TYPE_INVALID');
    }

    public static function notFound(): self
    {
        return new self('Shipment was not found.', 'SHIPMENT_NOT_FOUND');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function versionConflict(array $details = []): self
    {
        return new self(
            'This shipment was updated in another request. Refresh and try again.',
            'SHIPMENT_VERSION_CONFLICT',
            $details,
        );
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            'This shipment status change is not allowed.',
            'SHIPMENT_INVALID_TRANSITION',
            ['from' => $from, 'to' => $to],
        );
    }

    public static function quantityInvalid(): self
    {
        return new self('Shipment quantities are invalid.', 'SHIPMENT_QUANTITY_INVALID');
    }

    public static function alreadyDispatched(): self
    {
        return new self('This shipment has already been dispatched.', 'SHIPMENT_ALREADY_DISPATCHED');
    }

    public static function alreadyDelivered(): self
    {
        return new self('This shipment has already been delivered.', 'SHIPMENT_ALREADY_DELIVERED');
    }

    public static function alreadyCollected(): self
    {
        return new self('This pickup has already been collected.', 'SHIPMENT_ALREADY_COLLECTED');
    }

    public static function cancellationNotAllowed(): self
    {
        return new self('This shipment cannot be cancelled.', 'SHIPMENT_CANCELLATION_NOT_ALLOWED');
    }

    public static function providerUnavailable(): self
    {
        return new self('This shipment provider is not available.', 'SHIPMENT_PROVIDER_UNAVAILABLE');
    }

    public static function providerUnknown(): self
    {
        return new self('Unknown shipment provider.', 'SHIPMENT_PROVIDER_UNKNOWN');
    }

    public static function trackingUrlInvalid(): self
    {
        return new self('The tracking URL is not allowed.', 'SHIPMENT_TRACKING_URL_INVALID');
    }

    public static function trackingNumberInvalid(): self
    {
        return new self('The tracking number is invalid.', 'SHIPMENT_TRACKING_NUMBER_INVALID');
    }

    public static function pickupLocationInvalid(): self
    {
        return new self('The pickup location is not valid for this order.', 'PICKUP_LOCATION_INVALID');
    }

    public static function signatureInvalid(): self
    {
        return new self('Shipment webhook signature is invalid.', 'SHIPMENT_SIGNATURE_INVALID');
    }

    public static function payloadTooLarge(): self
    {
        return new self('Shipment webhook payload is too large.', 'SHIPMENT_PAYLOAD_TOO_LARGE');
    }

    public static function inventoryNotCommitted(): self
    {
        return new self('Inventory for this order has not been committed.', 'FULFILLMENT_ORDER_NOT_ELIGIBLE');
    }
}
