<?php

declare(strict_types=1);

namespace App\Domains\Payments\Listeners;

use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderExpired;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentFailureCategory;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Services\PaymentStateMachine;

final class ClosePaymentAttemptsOnOrderClosed
{
    public function __construct(private readonly PaymentStateMachine $states) {}

    public function handleExpired(OrderExpired $event): void
    {
        $this->close($event->orderPublicId, PaymentAttemptStatus::Expired, PaymentFailureCategory::Expired);
    }

    public function handleCancelled(OrderCancelled $event): void
    {
        $this->close($event->orderPublicId, PaymentAttemptStatus::Cancelled, PaymentFailureCategory::CancelledByCustomer);
    }

    private function close(string $orderPublicId, PaymentAttemptStatus $to, PaymentFailureCategory $category): void
    {
        $order = Order::query()->where('public_id', $orderPublicId)->first();
        if ($order === null) {
            return;
        }

        $attempts = PaymentAttempt::query()->where('order_id', $order->id)->get();
        foreach ($attempts as $attempt) {
            if (! $attempt->status->isActive()) {
                continue;
            }
            $this->states->transition($attempt, $to, $category->value);
            $attempt->failure_category = $category;
            $attempt->save();
        }
    }
}
