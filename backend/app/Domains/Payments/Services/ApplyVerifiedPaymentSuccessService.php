<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Inventory\Contracts\CheckoutInventoryService;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Domains\Inventory\Models\InventoryReservation;
use App\Domains\Orders\Actions\ApplyOrderManualReviewAction;
use App\Domains\Orders\Actions\ApplyOrderPaymentSucceededAction;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\OrderStatusReasonCode;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Events\InventoryCommittedForOrder;
use App\Domains\Payments\Events\PaymentNeedsManualReview;
use App\Domains\Payments\Events\PaymentSucceeded;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ApplyVerifiedPaymentSuccessService
{
    public function __construct(
        private readonly PaymentStateMachine $states,
        private readonly CheckoutInventoryService $inventory,
        private readonly ApplyOrderPaymentSucceededAction $confirmOrder,
        private readonly ApplyOrderManualReviewAction $manualReview,
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * @param  array{amount_minor: ?int, currency: ?string, provider_payment_id: string, provider_transaction_id: ?string}  $evidence
     */
    public function execute(PaymentAttempt $attempt, Order $order, array $evidence): void
    {
        if ($attempt->status === PaymentAttemptStatus::Succeeded && $order->payment_status === PaymentStatus::Paid) {
            return;
        }

        if ($evidence['provider_payment_id'] !== '' && $attempt->provider_payment_id !== null
            && $attempt->provider_payment_id !== $evidence['provider_payment_id']) {
            $this->sendToManualReview($attempt, $order, 'PROVIDER_REFERENCE_MISMATCH');

            return;
        }

        $amount = $evidence['amount_minor'];
        $currency = $evidence['currency'];
        if ($amount !== null && $amount !== $order->grand_total_minor) {
            $this->logger->critical('amount_mismatch', [
                'payment_attempt_public_id' => $attempt->public_id,
                'order_public_id' => $order->public_id,
                'expected_amount_minor' => $order->grand_total_minor,
                'reported_amount_minor' => $amount,
            ]);
            $this->sendToManualReview($attempt, $order, 'PAYMENT_AMOUNT_MISMATCH');

            return;
        }
        if ($currency !== null && strtoupper($currency) !== strtoupper($order->currency)) {
            $this->logger->critical('currency_mismatch', [
                'payment_attempt_public_id' => $attempt->public_id,
                'order_public_id' => $order->public_id,
                'expected_currency' => $order->currency,
                'reported_currency' => $currency,
            ]);
            $this->sendToManualReview($attempt, $order, 'PAYMENT_CURRENCY_MISMATCH');

            return;
        }

        if ($evidence['provider_payment_id'] !== '') {
            $attempt->provider_payment_id = $attempt->provider_payment_id ?? $evidence['provider_payment_id'];
        }
        if ($evidence['provider_transaction_id'] !== null) {
            $attempt->provider_transaction_id = $attempt->provider_transaction_id ?? $evidence['provider_transaction_id'];
        }
        $attempt->last_provider_sync_at = $this->clock->now();
        $attempt->save();

        $late = in_array($order->status, [OrderStatus::Expired, OrderStatus::Cancelled, OrderStatus::ManualReview], true)
            || $order->reservation_expires_at?->lte($this->clock->now()) === true;

        $reservations = InventoryReservation::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $order->public_id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $active = $reservations->filter(
            fn (InventoryReservation $reservation): bool => $reservation->status === InventoryReservationStatus::Active,
        );

        if ($late || $active->isEmpty() || $order->status === OrderStatus::Expired || $order->status === OrderStatus::Cancelled) {
            $reason = $order->status === OrderStatus::Cancelled
                ? 'PAYMENT_SUCCEEDED_AFTER_CANCELLATION'
                : 'PAYMENT_SUCCEEDED_AFTER_ORDER_EXPIRATION';
            $this->logger->critical('late_payment', [
                'payment_attempt_public_id' => $attempt->public_id,
                'order_public_id' => $order->public_id,
                'reason_code' => $reason,
            ]);
            $this->states->transition($attempt, PaymentAttemptStatus::Succeeded, $reason);
            $reasonCode = $order->status === OrderStatus::Cancelled
                ? OrderStatusReasonCode::PaymentSucceededAfterCancellation
                : OrderStatusReasonCode::PaymentSucceededAfterExpiration;
            $this->manualReview->execute($order, $reasonCode);
            $this->dispatchManualReview($attempt, $order, $reason);

            return;
        }

        try {
            foreach ($active as $reservation) {
                $this->inventory->commit(
                    $reservation,
                    'payment-commit:'.$order->public_id.':'.$reservation->reservation_key,
                );
            }
        } catch (Throwable $exception) {
            $this->logger->critical('inventory_commit_failed', [
                'payment_attempt_public_id' => $attempt->public_id,
                'order_public_id' => $order->public_id,
                'error_code' => $exception::class,
            ]);
            $this->sendToManualReview($attempt, $order, 'INVENTORY_COMMIT_FAILED');

            return;
        }

        $this->states->transition($attempt, PaymentAttemptStatus::Succeeded, 'verified_success');
        $this->confirmOrder->execute($order);

        $publicId = $attempt->public_id;
        $orderPublic = $order->public_id;
        $provider = $attempt->provider;
        $count = $active->count();
        DB::afterCommit(function () use ($publicId, $orderPublic, $provider, $count): void {
            event(new PaymentSucceeded($publicId, $orderPublic, $provider));
            event(new InventoryCommittedForOrder($orderPublic, $count));
        });

        $this->logger->info('payment_success', [
            'payment_attempt_public_id' => $publicId,
            'order_public_id' => $orderPublic,
            'provider' => $provider,
            'reservations_committed' => $count,
        ]);
    }

    private function sendToManualReview(PaymentAttempt $attempt, Order $order, string $reason): void
    {
        if ($attempt->status !== PaymentAttemptStatus::Succeeded) {
            $this->states->transition($attempt, PaymentAttemptStatus::ManualReview, $reason);
        }
        $code = match ($reason) {
            'PAYMENT_AMOUNT_MISMATCH' => OrderStatusReasonCode::PaymentAmountMismatch,
            'PAYMENT_CURRENCY_MISMATCH' => OrderStatusReasonCode::PaymentCurrencyMismatch,
            'INVENTORY_COMMIT_FAILED' => OrderStatusReasonCode::InventoryCommitFailed,
            'PAYMENT_SUCCEEDED_AFTER_CANCELLATION' => OrderStatusReasonCode::PaymentSucceededAfterCancellation,
            default => OrderStatusReasonCode::PaymentSucceededAfterExpiration,
        };
        $this->manualReview->execute($order, $code);
        $this->dispatchManualReview($attempt, $order, $reason);
    }

    private function dispatchManualReview(PaymentAttempt $attempt, Order $order, string $reason): void
    {
        $publicId = $attempt->public_id;
        $orderPublic = $order->public_id;
        DB::afterCommit(function () use ($publicId, $orderPublic, $reason): void {
            event(new PaymentNeedsManualReview($publicId, $orderPublic, $reason));
        });
    }
}
