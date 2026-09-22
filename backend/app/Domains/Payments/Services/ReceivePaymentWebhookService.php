<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Payments\DTOs\ProviderWebhookRequestData;
use App\Domains\Payments\DTOs\VerifiedProviderWebhookData;
use App\Domains\Payments\Enums\PaymentWebhookProcessingStatus;
use App\Domains\Payments\Enums\PaymentWebhookSignatureStatus;
use App\Domains\Payments\Events\PaymentWebhookReceived;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Models\PaymentWebhook;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReceivePaymentWebhookService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly ProcessVerifiedPaymentWebhookService $processor,
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * @param  array<string, string>  $safeHeaders
     */
    public function execute(string $providerCode, string $rawBody, array $safeHeaders, string $contentType): PaymentWebhook
    {
        $max = max(1, (int) config('payments.webhook_max_bytes', 65536));
        if (strlen($rawBody) > $max) {
            throw PaymentException::payloadTooLarge();
        }

        $provider = $this->providers->resolve($providerCode);
        $started = microtime(true);

        try {
            $verified = $provider->parseAndVerifyWebhook(new ProviderWebhookRequestData(
                rawBody: $rawBody,
                headers: $safeHeaders,
                contentType: $contentType,
            ));
        } catch (PaymentException $exception) {
            $this->logger->warning('signature_failure', [
                'provider' => $providerCode,
            ]);
            throw $exception;
        }

        if (! $verified->signatureVerified) {
            throw PaymentException::signatureInvalid();
        }

        $hash = hash('sha256', $rawBody);
        $webhook = $this->persist($providerCode, $verified, $hash, $safeHeaders);

        $this->logger->info('webhook_received', [
            'webhook_public_id' => $webhook->public_id,
            'provider' => $providerCode,
            'provider_event_id' => $verified->providerEventId,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        if ($webhook->processing_status === PaymentWebhookProcessingStatus::Processed) {
            $this->logger->info('webhook_deduplicated', [
                'webhook_public_id' => $webhook->public_id,
                'provider' => $providerCode,
                'provider_event_id' => $verified->providerEventId,
            ]);

            return $webhook;
        }

        return $this->processor->execute($webhook, $verified);
    }

    /**
     * @param  array<string, string>  $safeHeaders
     */
    private function persist(
        string $providerCode,
        VerifiedProviderWebhookData $verified,
        string $hash,
        array $safeHeaders,
    ): PaymentWebhook {
        $existing = PaymentWebhook::query()
            ->where('provider', $providerCode)
            ->where('provider_event_id', $verified->providerEventId)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $byHash = PaymentWebhook::query()
            ->where('provider', $providerCode)
            ->where('payload_hash', $hash)
            ->first();
        if ($byHash !== null) {
            return $byHash;
        }

        try {
            $webhook = PaymentWebhook::query()->create([
                'public_id' => (string) Str::uuid(),
                'provider' => $providerCode,
                'provider_event_id' => $verified->providerEventId,
                'payload_hash' => $hash,
                'normalized_event_type' => $verified->eventType->value,
                'provider_payment_id' => $verified->providerPaymentId,
                'signature_status' => PaymentWebhookSignatureStatus::Verified,
                'processing_status' => PaymentWebhookProcessingStatus::Verified,
                'received_at' => $this->clock->now(),
                'attempt_count' => 0,
                'safe_payload' => [
                    'normalized_status' => $verified->normalizedStatus->value,
                    'provider_status' => $verified->providerStatus,
                    'amount_minor' => $verified->amountMinor,
                    'currency' => $verified->currency,
                    'provider_transaction_id' => $verified->providerTransactionId,
                    'occurred_at' => $verified->occurredAt?->toIso8601String(),
                    'merchant_reference' => $verified->safeMetadata['merchant_reference'] ?? null,
                ],
                'safe_headers' => $safeHeaders,
            ]);
        } catch (QueryException) {
            $existing = PaymentWebhook::query()
                ->where('provider', $providerCode)
                ->where('provider_event_id', $verified->providerEventId)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
            throw PaymentException::providerUnavailable();
        }

        $publicId = $webhook->public_id;
        $eventId = $verified->providerEventId;
        DB::afterCommit(function () use ($publicId, $providerCode, $eventId): void {
            event(new PaymentWebhookReceived($publicId, $providerCode, $eventId));
        });

        return $webhook;
    }
}
