<?php

declare(strict_types=1);

namespace App\Domains\Orders\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

class OrderException extends DomainException implements ProvidesErrorDetails
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

    public static function notFound(): self
    {
        return new self('Order was not found.', 'ORDER_NOT_FOUND');
    }

    public static function checkoutNotFound(): self
    {
        return new self('Checkout session was not found.', 'ORDER_CHECKOUT_NOT_FOUND');
    }

    public static function quoteNotFound(): self
    {
        return new self('Checkout quote was not found.', 'ORDER_QUOTE_NOT_FOUND');
    }

    public static function quoteExpired(): self
    {
        return new self('This quote has expired. Refresh checkout to continue.', 'ORDER_QUOTE_EXPIRED');
    }

    public static function quoteSuperseded(): self
    {
        return new self('This quote is no longer current. Refresh checkout to continue.', 'ORDER_QUOTE_SUPERSEDED');
    }

    public static function quoteAlreadyConsumed(): self
    {
        return new self('This quote has already been used.', 'ORDER_QUOTE_ALREADY_CONSUMED');
    }

    public static function checkoutAlreadyConverted(): self
    {
        return new self('This checkout has already been converted to an order.', 'ORDER_CHECKOUT_ALREADY_CONVERTED');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function versionConflict(array $details = []): self
    {
        return new self(
            'This checkout was updated in another request. Refresh and try again.',
            'ORDER_VERSION_CONFLICT',
            $details,
        );
    }

    public static function quoteIntegrityFailed(): self
    {
        return new self('The quote failed an integrity check.', 'ORDER_QUOTE_INTEGRITY_FAILED');
    }

    public static function reservationMissing(): self
    {
        return new self('A required inventory reservation is missing.', 'ORDER_RESERVATION_MISSING');
    }

    public static function reservationExpired(): self
    {
        return new self('An inventory reservation has expired. Refresh checkout to continue.', 'ORDER_RESERVATION_EXPIRED');
    }

    public static function reservationInvalid(): self
    {
        return new self('An inventory reservation no longer matches this quote.', 'ORDER_RESERVATION_INVALID');
    }

    public static function financialInvariantFailed(): self
    {
        return new self('The quote totals are inconsistent.', 'ORDER_FINANCIAL_INVARIANT_FAILED');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function restrictionBlocked(array $details = []): self
    {
        return new self('A product in this quote cannot be ordered.', 'ORDER_RESTRICTION_BLOCKED', $details);
    }

    public static function idempotencyRequired(): self
    {
        return new self('A valid Idempotency-Key header is required.', 'ORDER_IDEMPOTENCY_REQUIRED');
    }

    public static function cancellationNotAllowed(): self
    {
        return new self('This order cannot be cancelled.', 'ORDER_CANCELLATION_NOT_ALLOWED');
    }

    public static function alreadyCancelled(): self
    {
        return new self('This order is already cancelled.', 'ORDER_ALREADY_CANCELLED');
    }

    public static function paymentWindowExpired(): self
    {
        return new self('The payment window for this order has ended.', 'ORDER_PAYMENT_WINDOW_EXPIRED');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            'This order status change is not allowed.',
            'ORDER_INVALID_TRANSITION',
            ['from' => $from, 'to' => $to],
        );
    }

    public static function fromCheckoutFailure(DomainException $exception): self
    {
        $details = $exception instanceof ProvidesErrorDetails ? $exception->errorDetails() : [];

        return match ($exception->errorCode()) {
            'CHECKOUT_SESSION_NOT_FOUND' => self::checkoutNotFound(),
            'CHECKOUT_QUOTE_NOT_FOUND' => self::quoteNotFound(),
            'CHECKOUT_QUOTE_EXPIRED', 'CHECKOUT_SESSION_EXPIRED' => self::quoteExpired(),
            'CHECKOUT_QUOTE_SUPERSEDED' => self::quoteSuperseded(),
            'CHECKOUT_VERSION_CONFLICT' => self::versionConflict($details),
            'CHECKOUT_SESSION_NOT_MUTABLE' => self::checkoutAlreadyConverted(),
            'CHECKOUT_RESTRICTION_BLOCKED' => self::restrictionBlocked($details),
            'CHECKOUT_QUOTE_INTEGRITY_FAILED' => self::quoteIntegrityFailed(),
            default => new self($exception->getMessage(), 'ORDER_QUOTE_NOT_FOUND', $details),
        };
    }
}
