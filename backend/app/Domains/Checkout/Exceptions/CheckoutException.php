<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

class CheckoutException extends DomainException implements ProvidesErrorDetails
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

    public static function cartEmpty(): self
    {
        return new self('The cart is empty.', 'CHECKOUT_CART_EMPTY');
    }

    public static function cartChanged(): self
    {
        return new self('The cart changed after this checkout was created.', 'CHECKOUT_CART_CHANGED');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function versionConflict(array $details = []): self
    {
        return new self(
            'This checkout was updated in another request. Refresh and try again.',
            'CHECKOUT_VERSION_CONFLICT',
            $details,
        );
    }

    public static function contactIncomplete(): self
    {
        return new self('Contact information is incomplete.', 'CHECKOUT_CONTACT_INCOMPLETE');
    }

    public static function addressIncomplete(): self
    {
        return new self('A delivery address is required for this fulfillment method.', 'CHECKOUT_ADDRESS_INCOMPLETE');
    }

    public static function fulfillmentRequired(): self
    {
        return new self('Select a fulfillment method to continue.', 'CHECKOUT_FULFILLMENT_REQUIRED');
    }

    public static function fulfillmentUnavailable(?string $reason = null): self
    {
        return new self(
            $reason ?? 'The selected fulfillment method is not available.',
            'CHECKOUT_FULFILLMENT_UNAVAILABLE',
        );
    }

    public static function deliveryZoneUnavailable(): self
    {
        return new self('Delivery is not available for this address.', 'CHECKOUT_DELIVERY_ZONE_UNAVAILABLE');
    }

    public static function productUnavailable(?string $itemId = null): self
    {
        return new self(
            'A product in this cart is no longer available.',
            'CHECKOUT_PRODUCT_UNAVAILABLE',
            $itemId !== null ? ['item_id' => $itemId] : [],
        );
    }

    public static function variantUnavailable(?string $itemId = null): self
    {
        return new self(
            'A selected option is no longer available.',
            'CHECKOUT_VARIANT_UNAVAILABLE',
            $itemId !== null ? ['item_id' => $itemId] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function insufficientStock(array $details = []): self
    {
        return new self(
            'Not enough stock is available for the quoted quantity.',
            'CHECKOUT_INSUFFICIENT_STOCK',
            $details,
        );
    }

    public static function quantityLimitExceeded(?string $itemId = null): self
    {
        return new self(
            'A quoted quantity exceeds the allowed limit.',
            'CHECKOUT_QUANTITY_LIMIT_EXCEEDED',
            $itemId !== null ? ['item_id' => $itemId] : [],
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function restrictionBlocked(array $details = []): self
    {
        return new self(
            'A product in this cart cannot be quoted.',
            'CHECKOUT_RESTRICTION_BLOCKED',
            $details,
        );
    }

    public static function quoteExpired(): self
    {
        return new self('This quote has expired.', 'CHECKOUT_QUOTE_EXPIRED');
    }

    public static function sessionExpired(): self
    {
        return new self('This checkout session has expired.', 'CHECKOUT_SESSION_EXPIRED');
    }

    public static function quoteSuperseded(): self
    {
        return new self('This quote is no longer current.', 'CHECKOUT_QUOTE_SUPERSEDED');
    }

    public static function currencyMismatch(): self
    {
        return new self('The checkout currency is not supported.', 'CHECKOUT_CURRENCY_MISMATCH');
    }

    public static function sessionNotFound(): self
    {
        return new self('Checkout session was not found.', 'CHECKOUT_SESSION_NOT_FOUND');
    }

    public static function quoteNotFound(): self
    {
        return new self('Checkout quote was not found.', 'CHECKOUT_QUOTE_NOT_FOUND');
    }

    public static function quoteIntegrityFailed(): self
    {
        return new self('The quote failed an integrity check.', 'CHECKOUT_QUOTE_INTEGRITY_FAILED');
    }

    public static function notMutable(): self
    {
        return new self('This checkout session can no longer be changed.', 'CHECKOUT_SESSION_NOT_MUTABLE');
    }

    public static function refreshLimit(): self
    {
        return new self('This checkout has reached the quote refresh limit.', 'CHECKOUT_REFRESH_LIMIT');
    }
}
