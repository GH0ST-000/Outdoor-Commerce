<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers;

use App\Domains\Payments\Contracts\PaymentProvider;
use App\Domains\Payments\DTOs\CancelProviderPaymentResultData;
use App\Domains\Payments\DTOs\CreateProviderPaymentRequestData;
use App\Domains\Payments\DTOs\CreateProviderPaymentResultData;
use App\Domains\Payments\DTOs\ProviderPaymentReferenceData;
use App\Domains\Payments\DTOs\ProviderPaymentStatusResultData;
use App\Domains\Payments\DTOs\ProviderWebhookRequestData;
use App\Domains\Payments\DTOs\VerifiedProviderWebhookData;
use App\Domains\Payments\Enums\PaymentActionType;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentNormalizedEventType;
use App\Domains\Payments\Enums\PaymentProviderCode;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Exceptions\PaymentProviderTimeoutException;
use App\Domains\Payments\Support\PaymentRedirectUrlValidator;
use App\Domains\Payments\Support\TestPaymentSignature;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;

/**
 * Development-only hosted-redirect provider. Disabled in production.
 * Scenarios are selected from config(`payments.test.scenario`).
 */
final class TestHostedPaymentProvider implements PaymentProvider
{
    /**
     * @var array<string, array{status: PaymentAttemptStatus, provider: string, amount: ?int, currency: ?string}>
     */
    private static array $store = [];

    public function __construct(
        private readonly Application $app,
        private readonly Clock $clock,
        private readonly TestPaymentSignature $signatures,
        private readonly PaymentRedirectUrlValidator $redirects,
    ) {}

    public function code(): string
    {
        return PaymentProviderCode::Test->value;
    }

    public function createPayment(CreateProviderPaymentRequestData $request): CreateProviderPaymentResultData
    {
        $this->guard();
        $scenario = (string) config('payments.test.scenario', 'success');

        if ($scenario === 'timeout') {
            throw new PaymentProviderTimeoutException;
        }

        $providerPaymentId = 'testpay_'.str_replace('-', '', $request->merchantReference);
        $status = match ($scenario) {
            'unknown' => PaymentAttemptStatus::Unknown,
            'failure' => PaymentAttemptStatus::RequiresAction,
            default => PaymentAttemptStatus::RequiresAction,
        };

        $frontend = rtrim((string) config('payments.frontend_url'), '/');
        $path = (string) config('payments.test.hosted_path', '/payment/test');
        $redirect = $frontend.$path.'/'.$request->merchantReference;

        if ($scenario !== 'unknown') {
            $redirect = $this->redirects->validate($redirect);
        }

        self::$store[$providerPaymentId] = [
            'status' => $status,
            'provider' => $status->value,
            'amount' => $request->amountMinor,
            'currency' => $request->currency,
        ];

        return new CreateProviderPaymentResultData(
            providerPaymentId: $providerPaymentId,
            providerTransactionId: 'testtxn_'.substr($providerPaymentId, 8, 16),
            normalizedStatus: $status,
            providerStatus: $status->value,
            action: $scenario === 'unknown' ? PaymentActionType::None : PaymentActionType::Redirect,
            redirectUrl: $scenario === 'unknown' ? null : $redirect,
            expiresAt: $this->clock->now()->addMinutes(20),
            safeMetadata: ['scenario' => $scenario],
        );
    }

    public function fetchPaymentStatus(ProviderPaymentReferenceData $reference): ProviderPaymentStatusResultData
    {
        $this->guard();
        $id = $reference->providerPaymentId ?? 'testpay_'.str_replace('-', '', $reference->merchantReference);
        $stored = self::$store[$id] ?? [
            'status' => PaymentAttemptStatus::RequiresAction,
            'provider' => 'requires_action',
            'amount' => null,
            'currency' => null,
        ];

        $scenario = (string) config('payments.test.scenario', 'success');
        if ($scenario === 'processing_then_success') {
            $stored['status'] = PaymentAttemptStatus::Succeeded;
            $stored['provider'] = 'succeeded';
        }

        return new ProviderPaymentStatusResultData(
            providerPaymentId: $id,
            providerTransactionId: $reference->providerTransactionId,
            normalizedStatus: $stored['status'],
            providerStatus: $stored['provider'],
            amountMinor: $stored['amount'],
            currency: $stored['currency'],
            occurredAt: $this->clock->now(),
        );
    }

    public function parseAndVerifyWebhook(ProviderWebhookRequestData $request): VerifiedProviderWebhookData
    {
        $this->guard();
        $secret = (string) config('payments.test.secret');
        $signature = $request->headers['x-test-signature'] ?? '';
        $timestamp = (int) ($request->headers['x-test-timestamp'] ?? 0);
        $tolerance = (int) config('payments.webhook_timestamp_tolerance_seconds', 300);

        if ($signature === '' || $timestamp === 0) {
            throw PaymentException::signatureInvalid();
        }

        if (! $this->signatures->verify($request->rawBody, $signature, $timestamp, $secret, $tolerance)) {
            throw PaymentException::signatureInvalid();
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->rawBody, true, 512, JSON_THROW_ON_ERROR);

        $status = $this->mapStatus((string) ($payload['status'] ?? 'unknown'));
        $event = $this->mapEvent((string) ($payload['event_type'] ?? $status->value));
        $occurred = isset($payload['occurred_at']) && is_string($payload['occurred_at'])
            ? CarbonImmutable::parse($payload['occurred_at'])
            : $this->clock->now();

        $providerPaymentId = (string) ($payload['payment_id'] ?? '');
        if ($providerPaymentId !== '') {
            self::$store[$providerPaymentId] = [
                'status' => $status,
                'provider' => (string) ($payload['status'] ?? $status->value),
                'amount' => isset($payload['amount_minor']) ? (int) $payload['amount_minor'] : null,
                'currency' => isset($payload['currency']) ? (string) $payload['currency'] : null,
            ];
        }

        return new VerifiedProviderWebhookData(
            providerEventId: (string) ($payload['event_id'] ?? (string) Str::uuid()),
            providerPaymentId: $providerPaymentId,
            providerTransactionId: isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            eventType: $event,
            normalizedStatus: $status,
            providerStatus: (string) ($payload['status'] ?? $status->value),
            amountMinor: isset($payload['amount_minor']) ? (int) $payload['amount_minor'] : null,
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
            occurredAt: $occurred,
            safeMetadata: [
                'event_type' => (string) ($payload['event_type'] ?? ''),
                'merchant_reference' => isset($payload['merchant_reference']) ? (string) $payload['merchant_reference'] : null,
            ],
            signatureVerified: true,
        );
    }

    public function cancelPayment(ProviderPaymentReferenceData $reference): CancelProviderPaymentResultData
    {
        $this->guard();
        $id = (string) $reference->providerPaymentId;
        if ($id !== '' && isset(self::$store[$id])) {
            self::$store[$id]['status'] = PaymentAttemptStatus::Cancelled;
            self::$store[$id]['provider'] = 'cancelled';
        }

        return new CancelProviderPaymentResultData(
            normalizedStatus: PaymentAttemptStatus::Cancelled,
            providerStatus: 'cancelled',
            cancelled: true,
        );
    }

    public static function resetStore(): void
    {
        self::$store = [];
    }

    private function guard(): void
    {
        if ($this->app->environment('production') || ! (bool) config('payments.test.enabled', false)) {
            throw PaymentException::testProviderForbidden();
        }
    }

    private function mapStatus(string $status): PaymentAttemptStatus
    {
        return PaymentAttemptStatus::tryFrom($status) ?? match ($status) {
            'success', 'paid', 'captured' => PaymentAttemptStatus::Succeeded,
            'decline', 'declined' => PaymentAttemptStatus::Failed,
            'cancel', 'canceled' => PaymentAttemptStatus::Cancelled,
            default => PaymentAttemptStatus::Unknown,
        };
    }

    private function mapEvent(string $event): PaymentNormalizedEventType
    {
        return PaymentNormalizedEventType::tryFrom($event) ?? match ($event) {
            'payment.succeeded', 'succeeded' => PaymentNormalizedEventType::Succeeded,
            'payment.failed', 'failed' => PaymentNormalizedEventType::Failed,
            'payment.cancelled', 'cancelled' => PaymentNormalizedEventType::Cancelled,
            'payment.expired', 'expired' => PaymentNormalizedEventType::Expired,
            'payment.processing', 'processing' => PaymentNormalizedEventType::Processing,
            default => PaymentNormalizedEventType::Unknown,
        };
    }
}
