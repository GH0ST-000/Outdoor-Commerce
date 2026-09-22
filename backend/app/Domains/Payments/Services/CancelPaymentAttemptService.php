<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\Actions\AssertOrderAccessAction;
use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\DTOs\ProviderPaymentReferenceData;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentFailureCategory;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Support\PaymentDeadlockRetry;
use App\Domains\Payments\Support\PaymentLogger;
use Illuminate\Support\Facades\DB;

final class CancelPaymentAttemptService
{
    public function __construct(
        private readonly AssertOrderAccessAction $assertAccess,
        private readonly PaymentProviderRegistry $providers,
        private readonly ApplyPaymentFailureService $failure,
        private readonly PaymentDeadlockRetry $retry,
        private readonly PaymentLogger $logger,
    ) {}

    public function execute(OrderActorData $actor, string $attemptPublicId): PaymentAttempt
    {
        $attempt = PaymentAttempt::query()->where('public_id', $attemptPublicId)->first();
        if ($attempt === null) {
            throw PaymentException::notFound();
        }
        $order = Order::query()->whereKey($attempt->order_id)->first();
        if ($order === null) {
            throw PaymentException::notFound();
        }
        $this->assertAccess->execute($order, $actor);

        if (in_array($attempt->status, [PaymentAttemptStatus::Cancelled, PaymentAttemptStatus::Expired, PaymentAttemptStatus::Failed], true)) {
            return $attempt;
        }
        if ($attempt->status === PaymentAttemptStatus::Succeeded) {
            throw PaymentException::cancellationNotAllowed();
        }
        if (! $attempt->status->isCancellable()) {
            throw PaymentException::cancellationNotAllowed();
        }

        if ($attempt->provider_payment_id !== null) {
            try {
                $this->providers->resolve($attempt->provider)->cancelPayment(new ProviderPaymentReferenceData(
                    merchantReference: $attempt->public_id,
                    providerPaymentId: $attempt->provider_payment_id,
                    providerTransactionId: $attempt->provider_transaction_id,
                ));
            } catch (PaymentException $exception) {
                $this->logger->warning('provider_cancel_failed', [
                    'payment_attempt_public_id' => $attempt->public_id,
                    'error_code' => $exception->errorCode(),
                ]);
            }
        }

        return $this->retry->run(function () use ($attempt): PaymentAttempt {
            return DB::transaction(function () use ($attempt): PaymentAttempt {
                $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
                if ($locked->status === PaymentAttemptStatus::Cancelled) {
                    return $locked;
                }
                if (! $locked->status->isCancellable()) {
                    throw PaymentException::cancellationNotAllowed();
                }
                $this->failure->execute(
                    $locked,
                    $order,
                    PaymentAttemptStatus::Cancelled,
                    PaymentFailureCategory::CancelledByCustomer,
                    'cancelled',
                );

                return $locked->fresh() ?? $locked;
            });
        });
    }
}
