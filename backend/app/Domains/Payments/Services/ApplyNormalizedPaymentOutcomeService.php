<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentFailureCategory;
use App\Domains\Payments\Events\PaymentProcessing;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Support\PaymentLogger;
use Illuminate\Support\Facades\DB;

final class ApplyNormalizedPaymentOutcomeService
{
    public function __construct(
        private readonly ApplyVerifiedPaymentSuccessService $success,
        private readonly ApplyPaymentFailureService $failure,
        private readonly PaymentStateMachine $states,
        private readonly PaymentLogger $logger,
    ) {}

    public function execute(
        PaymentAttempt $attempt,
        Order $order,
        PaymentAttemptStatus $status,
        ?int $amount,
        ?string $currency,
        string $providerPaymentId,
        ?string $transactionId,
    ): void {
        if ($attempt->status === PaymentAttemptStatus::Succeeded && $status !== PaymentAttemptStatus::Succeeded) {
            $this->logger->warning('ignored_downgrade', [
                'payment_attempt_public_id' => $attempt->public_id,
                'to' => $status->value,
            ]);

            return;
        }

        match ($status) {
            PaymentAttemptStatus::Succeeded => $this->success->execute($attempt, $order, [
                'amount_minor' => $amount,
                'currency' => $currency,
                'provider_payment_id' => $providerPaymentId,
                'provider_transaction_id' => $transactionId,
            ]),
            PaymentAttemptStatus::Failed => $this->failure->execute(
                $attempt,
                $order,
                PaymentAttemptStatus::Failed,
                PaymentFailureCategory::Declined,
                'declined',
            ),
            PaymentAttemptStatus::Cancelled => $this->failure->execute(
                $attempt,
                $order,
                PaymentAttemptStatus::Cancelled,
                PaymentFailureCategory::CancelledByCustomer,
                'cancelled',
            ),
            PaymentAttemptStatus::Expired => $this->failure->execute(
                $attempt,
                $order,
                PaymentAttemptStatus::Expired,
                PaymentFailureCategory::Expired,
                'expired',
            ),
            PaymentAttemptStatus::Processing, PaymentAttemptStatus::Pending, PaymentAttemptStatus::RequiresAction => $this->advance($attempt, $status, $order),
            PaymentAttemptStatus::Unknown, PaymentAttemptStatus::ManualReview => $this->states->transition($attempt, $status, 'provider_status'),
            default => null,
        };
    }

    private function advance(PaymentAttempt $attempt, PaymentAttemptStatus $to, Order $order): void
    {
        if ($this->states->transition($attempt, $to, 'provider_status') && $to === PaymentAttemptStatus::Processing) {
            $publicId = $attempt->public_id;
            $orderPublic = $order->public_id;
            DB::afterCommit(function () use ($publicId, $orderPublic): void {
                event(new PaymentProcessing($publicId, $orderPublic));
            });
        }
    }
}
