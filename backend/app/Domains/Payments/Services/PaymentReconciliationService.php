<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\DTOs\ProviderPaymentReferenceData;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Events\PaymentReconciled;
use App\Domains\Payments\Exceptions\PaymentProviderTimeoutException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Models\PaymentWebhook;
use App\Domains\Payments\Support\PaymentDeadlockRetry;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;

final class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly ProcessVerifiedPaymentWebhookService $processor,
        private readonly ApplyNormalizedPaymentOutcomeService $outcome,
        private readonly PaymentDeadlockRetry $retry,
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int}
     */
    public function execute(?string $attemptPublicId = null): array
    {
        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;
        $minAge = max(0, (int) config('payments.reconcile_min_age_seconds', 30));
        $chunk = max(1, (int) config('payments.reconcile_chunk_size', 50));

        $query = PaymentAttempt::query()
            ->whereIn('status', [
                PaymentAttemptStatus::Pending->value,
                PaymentAttemptStatus::RequiresAction->value,
                PaymentAttemptStatus::Processing->value,
                PaymentAttemptStatus::Unknown->value,
                PaymentAttemptStatus::Created->value,
            ])
            ->where('updated_at', '<=', $this->clock->now()->subSeconds($minAge))
            ->orderBy('id')
            ->limit($chunk);

        if ($attemptPublicId !== null) {
            $query = PaymentAttempt::query()->where('public_id', $attemptPublicId);
        }

        foreach ($query->get() as $attempt) {
            $result = $this->reconcileOne($attempt);
            $processed++;
            if ($result === 'succeeded') {
                $succeeded++;
            } elseif ($result === 'failed') {
                $failed++;
            } else {
                $skipped++;
            }
        }

        $this->logger->info('reconciliation_result', [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        return compact('processed', 'succeeded', 'failed', 'skipped');
    }

    public function retryWebhook(string $webhookPublicId): void
    {
        $webhook = PaymentWebhook::query()->where('public_id', $webhookPublicId)->first();
        if ($webhook === null) {
            return;
        }

        $this->processor->execute($webhook);
    }

    private function reconcileOne(PaymentAttempt $attempt): string
    {
        if ($attempt->status === PaymentAttemptStatus::Succeeded) {
            return 'skipped';
        }

        try {
            $provider = $this->providers->resolve($attempt->provider);
            $result = $provider->fetchPaymentStatus(new ProviderPaymentReferenceData(
                merchantReference: $attempt->public_id,
                providerPaymentId: $attempt->provider_payment_id,
                providerTransactionId: $attempt->provider_transaction_id,
            ));
        } catch (PaymentProviderTimeoutException) {
            $this->logger->warning('reconcile_timeout', [
                'payment_attempt_public_id' => $attempt->public_id,
                'provider' => $attempt->provider,
            ]);

            return 'skipped';
        }

        $this->retry->run(function () use ($attempt, $result): void {
            DB::transaction(function () use ($attempt, $result): void {
                $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
                $locked->provider_status = $result->providerStatus;
                $locked->last_provider_sync_at = $this->clock->now();
                if ($locked->provider_payment_id === null) {
                    $locked->provider_payment_id = $result->providerPaymentId;
                }
                $locked->save();

                $this->outcome->execute(
                    $locked,
                    $order,
                    $result->normalizedStatus,
                    $result->amountMinor,
                    $result->currency,
                    $result->providerPaymentId,
                    $result->providerTransactionId,
                );
            });
        });

        $fresh = $attempt->fresh() ?? $attempt;
        $status = $fresh->status;
        $order = Order::query()->whereKey($attempt->order_id)->first();
        if ($order !== null) {
            event(new PaymentReconciled($attempt->public_id, $order->public_id, $status->value));
        }

        return match ($status) {
            PaymentAttemptStatus::Succeeded => 'succeeded',
            PaymentAttemptStatus::Failed, PaymentAttemptStatus::Cancelled, PaymentAttemptStatus::Expired => 'failed',
            default => 'skipped',
        };
    }
}
