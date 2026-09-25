<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\DTOs\VerifiedProviderWebhookData;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentWebhookProcessingStatus;
use App\Domains\Payments\Events\PaymentWebhookProcessed;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentWebhook;
use App\Domains\Payments\Support\PaymentDeadlockRetry;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;

final class ProcessVerifiedPaymentWebhookService
{
    public function __construct(
        private readonly ApplyNormalizedPaymentOutcomeService $outcome,
        private readonly PaymentDeadlockRetry $retry,
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    public function execute(PaymentWebhook $webhook, ?VerifiedProviderWebhookData $verified = null): PaymentWebhook
    {
        return $this->retry->run(function () use ($webhook, $verified): PaymentWebhook {
            return DB::transaction(function () use ($webhook, $verified): PaymentWebhook {
                $locked = PaymentWebhook::query()->whereKey($webhook->id)->lockForUpdate()->firstOrFail();
                if (in_array($locked->processing_status, [
                    PaymentWebhookProcessingStatus::Processed,
                    PaymentWebhookProcessingStatus::Ignored,
                ], true)) {
                    return $locked;
                }

                $locked->processing_status = PaymentWebhookProcessingStatus::Processing;
                $locked->attempt_count = $locked->attempt_count + 1;
                $locked->save();

                $payload = is_array($locked->safe_payload) ? $locked->safe_payload : [];
                if ($verified instanceof VerifiedProviderWebhookData) {
                    $status = $verified->normalizedStatus;
                    $providerPaymentId = $verified->providerPaymentId;
                    $amount = $verified->amountMinor;
                    $currency = $verified->currency;
                    $transactionId = $verified->providerTransactionId;
                    $providerStatus = $verified->providerStatus;
                    $merchant = $verified->safeMetadata['merchant_reference'] ?? null;
                } else {
                    $status = PaymentAttemptStatus::tryFrom((string) ($payload['normalized_status'] ?? ''))
                        ?? PaymentAttemptStatus::Unknown;
                    $providerPaymentId = $locked->provider_payment_id;
                    $amount = isset($payload['amount_minor']) ? (int) $payload['amount_minor'] : null;
                    $currency = isset($payload['currency']) ? (string) $payload['currency'] : null;
                    $transactionId = isset($payload['provider_transaction_id']) ? (string) $payload['provider_transaction_id'] : null;
                    $providerStatus = isset($payload['provider_status']) ? (string) $payload['provider_status'] : null;
                    $merchant = $payload['merchant_reference'] ?? null;
                }

                if ($providerPaymentId === null || $providerPaymentId === '') {
                    return $this->finish($locked, PaymentWebhookProcessingStatus::Unmatched, 'PAYMENT_WEBHOOK_UNMATCHED');
                }

                $attempt = PaymentAttempt::query()
                    ->where('provider', $locked->provider)
                    ->where('provider_payment_id', $providerPaymentId)
                    ->lockForUpdate()
                    ->first();

                if ($attempt === null && is_string($merchant) && $merchant !== '') {
                    $attempt = PaymentAttempt::query()
                        ->where('public_id', $merchant)
                        ->lockForUpdate()
                        ->first();
                }

                if ($attempt === null) {
                    $this->logger->warning('unmatched_webhook', [
                        'webhook_public_id' => $locked->public_id,
                        'provider' => $locked->provider,
                        'provider_event_id' => $locked->provider_event_id,
                    ]);

                    return $this->finish($locked, PaymentWebhookProcessingStatus::Unmatched, 'PAYMENT_WEBHOOK_UNMATCHED');
                }

                $order = Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
                if (is_string($providerStatus) && $providerStatus !== '') {
                    $attempt->provider_status = $providerStatus;
                }
                $attempt->last_provider_sync_at = $this->clock->now();
                $attempt->save();

                $this->outcome->execute($attempt, $order, $status, $amount, $currency, (string) $providerPaymentId, $transactionId, is_string($merchant) ? $merchant : null);

                $processed = $this->finish($locked, PaymentWebhookProcessingStatus::Processed);
                $publicId = $processed->public_id;
                $provider = $processed->provider;
                DB::afterCommit(function () use ($publicId, $provider): void {
                    event(new PaymentWebhookProcessed($publicId, $provider, PaymentWebhookProcessingStatus::Processed->value));
                });

                return $processed;
            });
        });
    }

    private function finish(
        PaymentWebhook $webhook,
        PaymentWebhookProcessingStatus $status,
        ?string $error = null,
    ): PaymentWebhook {
        $webhook->processing_status = $status;
        $webhook->last_error_code = $error;
        if ($status === PaymentWebhookProcessingStatus::Processed || $status === PaymentWebhookProcessingStatus::Ignored) {
            $webhook->processed_at = $this->clock->now();
        }
        if (in_array($status, [PaymentWebhookProcessingStatus::Failed, PaymentWebhookProcessingStatus::Unmatched, PaymentWebhookProcessingStatus::ManualReview], true)) {
            $webhook->failed_at = $this->clock->now();
        }
        $webhook->save();

        return $webhook;
    }
}
