<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\Actions\ApplyOrderPaymentReturnedToPendingAction;
use App\Domains\Orders\Actions\ExpireOrderIfDueAction;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentFailureCategory;
use App\Domains\Payments\Events\PaymentCancelled;
use App\Domains\Payments\Events\PaymentExpired;
use App\Domains\Payments\Events\PaymentFailed;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\DB;

final class ApplyPaymentFailureService
{
    public function __construct(
        private readonly PaymentStateMachine $states,
        private readonly ApplyOrderPaymentReturnedToPendingAction $returnPending,
        private readonly ExpireOrderIfDueAction $expireOrder,
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
    ) {}

    public function execute(
        PaymentAttempt $attempt,
        Order $order,
        PaymentAttemptStatus $to,
        PaymentFailureCategory $category,
        ?string $failureCode,
    ): void {
        if ($attempt->status === PaymentAttemptStatus::Succeeded) {
            return;
        }

        $this->states->transition($attempt, $to, $category->value);
        $attempt->failure_category = $category;
        $attempt->failure_code = $failureCode;
        $attempt->failure_message = $category->value;
        $attempt->last_provider_sync_at = $this->clock->now();
        $attempt->save();

        $stillActive = PaymentAttempt::query()
            ->where('order_id', $order->id)
            ->whereKeyNot($attempt->id)
            ->get()
            ->contains(fn (PaymentAttempt $other): bool => $other->status->isActive() || $other->status === PaymentAttemptStatus::Succeeded);

        if (! $stillActive) {
            $this->returnPending->execute($order, PaymentStatus::Unpaid);
            $this->expireOrder->execute($order, true);
        }

        $publicId = $attempt->public_id;
        $orderPublic = $order->public_id;
        DB::afterCommit(function () use ($publicId, $orderPublic, $to, $category): void {
            match ($to) {
                PaymentAttemptStatus::Cancelled => event(new PaymentCancelled($publicId, $orderPublic)),
                PaymentAttemptStatus::Expired => event(new PaymentExpired($publicId, $orderPublic)),
                default => event(new PaymentFailed($publicId, $orderPublic, $category->value)),
            };
        });

        $this->logger->info('payment_failure', [
            'payment_attempt_public_id' => $publicId,
            'order_public_id' => $orderPublic,
            'failure_category' => $category->value,
            'status' => $to->value,
        ]);
    }
}
