<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

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
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Payments\Support\PaymentRedirectUrlValidator;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

final class BankOfGeorgiaPaymentProvider implements PaymentProvider
{
    public function __construct(
        private readonly BankOfGeorgiaConfigurationValidator $config,
        private readonly BankOfGeorgiaHttpClient $http,
        private readonly BankOfGeorgiaRequestFactory $requests,
        private readonly BankOfGeorgiaResponseMapper $responses,
        private readonly BankOfGeorgiaCallbackVerifier $signatures,
        private readonly PaymentRedirectUrlValidator $redirects,
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    public function code(): string
    {
        return PaymentProviderCode::Bog->value;
    }

    public function createPayment(CreateProviderPaymentRequestData $request): CreateProviderPaymentResultData
    {
        $this->config->assertReady();
        $prepared = $this->requests->createOrder($request);
        $response = $this->http->createOrder($prepared['json'], $prepared['headers']);
        $payload = $this->json($response, 'create_order');
        $mapped = $this->responses->mapCreateOrder($payload);

        $hosts = config('payments.providers.bog.allowed_redirect_hosts', ['payment.bog.ge']);
        $redirect = $this->redirects->validate(
            $mapped['redirect'],
            is_array($hosts) ? array_values(array_map(static fn (mixed $h): string => (string) $h, $hosts)) : ['payment.bog.ge'],
        );

        $this->logger->info('bog_provider_payment_created', [
            'provider' => 'bog',
            'merchant_reference' => $request->merchantReference,
        ]);

        return new CreateProviderPaymentResultData(
            providerPaymentId: $mapped['id'],
            providerTransactionId: null,
            normalizedStatus: PaymentAttemptStatus::RequiresAction,
            providerStatus: 'created',
            action: PaymentActionType::Redirect,
            redirectUrl: $redirect,
            expiresAt: $this->clock->now()->addMinutes($prepared['ttl_minutes']),
            safeMetadata: [
                'details_present' => $mapped['details'] !== null,
            ],
        );
    }

    public function fetchPaymentStatus(ProviderPaymentReferenceData $reference): ProviderPaymentStatusResultData
    {
        $this->config->assertReady();
        $id = $reference->providerPaymentId;
        if ($id === null || $id === '') {
            throw PaymentException::malformedProviderResponse();
        }

        $response = $this->http->paymentDetails($id);
        $payload = $this->json($response, 'payment_details');
        $mapped = $this->responses->mapPaymentDetails($payload);

        if ($mapped['merchant_reference'] !== null && $mapped['merchant_reference'] !== $reference->merchantReference) {
            $this->logger->critical('bog_merchant_reference_mismatch', [
                'provider' => 'bog',
                'merchant_reference' => $reference->merchantReference,
            ]);
            $mapped['normalized_status'] = PaymentAttemptStatus::ManualReview;
        }

        return new ProviderPaymentStatusResultData(
            providerPaymentId: $mapped['provider_payment_id'],
            providerTransactionId: $mapped['provider_transaction_id'],
            normalizedStatus: $mapped['normalized_status'],
            providerStatus: $mapped['provider_status'],
            amountMinor: $mapped['amount_minor'],
            currency: $mapped['currency'],
            occurredAt: $mapped['occurred_at'] ?? $this->clock->now(),
            safeMetadata: [
                'merchant_reference' => $mapped['merchant_reference'],
                'transfer_amount_minor' => $mapped['transfer_amount_minor'],
            ],
        );
    }

    public function parseAndVerifyWebhook(ProviderWebhookRequestData $request): VerifiedProviderWebhookData
    {
        $this->config->assertReady();
        $signature = $request->headers['callback-signature'] ?? '';
        $this->signatures->verify($request->rawBody, $signature);

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($request->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw PaymentException::malformedProviderResponse();
        }

        $event = is_string($payload['event'] ?? null) ? $payload['event'] : '';
        $mapped = $this->responses->mapPaymentDetails($payload);

        if ($event !== '' && $event !== 'order_payment') {
            $mapped['normalized_status'] = PaymentAttemptStatus::Unknown;
            $mapped['event_type'] = PaymentNormalizedEventType::Unknown;
        }

        $occurred = $mapped['occurred_at'];
        if (is_string($payload['zoned_request_time'] ?? null)) {
            $occurred = CarbonImmutable::parse($payload['zoned_request_time']);
        }

        $eventId = hash('sha256', implode('|', [
            'bog',
            $mapped['provider_payment_id'],
            $event,
            $mapped['provider_status'],
            (string) $mapped['provider_transaction_id'],
            $occurred?->toIso8601String() ?? '',
            hash('sha256', $request->rawBody),
        ]));

        $this->logger->info('bog_callback_received', [
            'provider' => 'bog',
            'provider_event_id' => $eventId,
            'provider_status' => $mapped['provider_status'],
        ]);

        return new VerifiedProviderWebhookData(
            providerEventId: $eventId,
            providerPaymentId: $mapped['provider_payment_id'],
            providerTransactionId: $mapped['provider_transaction_id'],
            eventType: $mapped['event_type'],
            normalizedStatus: $mapped['normalized_status'],
            providerStatus: $mapped['provider_status'],
            amountMinor: $mapped['amount_minor'],
            currency: $mapped['currency'],
            occurredAt: $occurred,
            safeMetadata: [
                'merchant_reference' => $mapped['merchant_reference'],
                'event' => $event,
                'transfer_amount_minor' => $mapped['transfer_amount_minor'],
            ],
            signatureVerified: true,
        );
    }

    public function cancelPayment(ProviderPaymentReferenceData $reference): CancelProviderPaymentResultData
    {
        return new CancelProviderPaymentResultData(
            normalizedStatus: PaymentAttemptStatus::Cancelled,
            providerStatus: 'cancelled_locally',
            cancelled: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response, string $operation): array
    {
        if ($response->failed()) {
            $this->logger->warning('bog_'.$operation.'_http_error', [
                'provider' => 'bog',
                'http_status' => $response->status(),
            ]);
            if ($response->status() >= 500 || $response->status() === 0) {
                throw PaymentException::providerUnavailable();
            }
            throw PaymentException::malformedProviderResponse();
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw PaymentException::malformedProviderResponse();
        }

        return $payload;
    }
}
