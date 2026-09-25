<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

class PaymentException extends DomainException implements ProvidesErrorDetails
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
        return new self('Payment was not found.', 'PAYMENT_NOT_FOUND');
    }

    public static function orderNotFound(): self
    {
        return new self('Order was not found.', 'PAYMENT_ORDER_NOT_FOUND');
    }

    public static function idempotencyRequired(): self
    {
        return new self('A valid Idempotency-Key header is required.', 'PAYMENT_IDEMPOTENCY_REQUIRED');
    }

    public static function methodUnavailable(): self
    {
        return new self('This payment method is not available for the order.', 'PAYMENT_METHOD_UNAVAILABLE');
    }

    public static function providerUnavailable(): self
    {
        return new self('This payment provider is not available.', 'PAYMENT_PROVIDER_UNAVAILABLE');
    }

    public static function providerUnknown(): self
    {
        return new self('Unknown payment provider.', 'PAYMENT_PROVIDER_UNKNOWN');
    }

    public static function currencyUnsupported(): self
    {
        return new self('This payment method does not support the order currency.', 'PAYMENT_CURRENCY_UNSUPPORTED');
    }

    public static function amountUnsupported(): self
    {
        return new self('This order total is outside the payment method limits.', 'PAYMENT_AMOUNT_UNSUPPORTED');
    }

    public static function orderNotPayable(): self
    {
        return new self('This order cannot accept a payment attempt.', 'PAYMENT_ORDER_NOT_PAYABLE');
    }

    public static function alreadyPaid(): self
    {
        return new self('This order is already paid.', 'PAYMENT_ALREADY_PAID');
    }

    public static function activeAttemptExists(): self
    {
        return new self('An active payment attempt already exists for this order.', 'PAYMENT_ACTIVE_ATTEMPT_EXISTS');
    }

    public static function reservationMissing(): self
    {
        return new self('Required inventory reservations are missing or inactive.', 'PAYMENT_RESERVATION_MISSING');
    }

    public static function paymentWindowExpired(): self
    {
        return new self('The payment window for this order has ended.', 'PAYMENT_WINDOW_EXPIRED');
    }

    public static function invalidRedirect(): self
    {
        return new self('The payment provider returned an invalid redirect.', 'PAYMENT_INVALID_REDIRECT');
    }

    public static function cancellationNotAllowed(): self
    {
        return new self('This payment attempt cannot be cancelled.', 'PAYMENT_CANCELLATION_NOT_ALLOWED');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            'This payment status change is not allowed.',
            'PAYMENT_INVALID_TRANSITION',
            ['from' => $from, 'to' => $to],
        );
    }

    public static function signatureInvalid(): self
    {
        return new self('Payment webhook signature is invalid.', 'PAYMENT_SIGNATURE_INVALID');
    }

    public static function payloadTooLarge(): self
    {
        return new self('Payment webhook payload is too large.', 'PAYMENT_PAYLOAD_TOO_LARGE');
    }

    public static function testProviderForbidden(): self
    {
        return new self('The test payment provider cannot run in this environment.', 'PAYMENT_TEST_PROVIDER_FORBIDDEN');
    }

    public static function versionConflict(): self
    {
        return new self('The order changed in another request. Refresh and try again.', 'PAYMENT_VERSION_CONFLICT');
    }

    public static function simulateForbidden(): self
    {
        return new self('Test payment simulation is not available.', 'PAYMENT_SIMULATE_FORBIDDEN');
    }

    public static function configurationInvalid(): self
    {
        return new self('The payment provider is not configured correctly.', 'PAYMENT_PROVIDER_CONFIGURATION');
    }

    public static function authenticationFailed(): self
    {
        return new self('The payment provider could not authenticate this request.', 'PAYMENT_PROVIDER_AUTHENTICATION');
    }

    public static function malformedProviderResponse(): self
    {
        return new self('The payment provider returned an unusable response.', 'PAYMENT_PROVIDER_MALFORMED_RESPONSE');
    }

    public static function basketMismatch(): self
    {
        return new self('The order basket does not reconcile with the payment total.', 'PAYMENT_BASKET_MISMATCH');
    }

    public static function moneyInvalid(): self
    {
        return new self('The payment amount cannot be expressed for this provider.', 'PAYMENT_MONEY_INVALID');
    }

    public static function ttlInsufficient(): self
    {
        return new self('Not enough time remains in the payment window to start a bank payment.', 'PAYMENT_TTL_INSUFFICIENT');
    }
}
