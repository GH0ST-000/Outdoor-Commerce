<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentActionType;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentFailureCategory;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Shared\Support\Clock;

final class PaymentPresenter
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * @return array{data: array<string, mixed>}
     */
    public function presentAttempt(PaymentAttempt $attempt, Order $order): array
    {
        $attempt->loadMissing('order');

        return [
            'data' => [
                'id' => $attempt->public_id,
                'order_id' => $order->public_id,
                'status' => $attempt->status->value,
                'payment_method' => [
                    'code' => $attempt->payment_method_code,
                    'name' => $attempt->payment_method_code,
                ],
                'amount' => [
                    'amount_minor' => $attempt->amount_minor,
                    'currency' => $attempt->currency,
                ],
                'action' => $this->action($attempt),
                'failure' => $this->failure($attempt),
                'order' => [
                    'status' => $order->status->value,
                    'payment_status' => $order->payment_status->value,
                    'payment_expires_at' => $order->reservation_expires_at?->toIso8601String(),
                ],
            ],
        ];
    }

    /**
     * @param  array{data: array<string, mixed>}  $orderPayload
     * @return array{data: array<string, mixed>}
     */
    public function enrichOrder(array $orderPayload, Order $order, ?PaymentAttempt $current): array
    {
        $canPay = $this->canPay($order);
        $orderPayload['data']['can_pay'] = $canPay;
        $orderPayload['data']['can_retry_payment'] = $canPay && $this->hasTerminalFailedAttempt($order);
        $orderPayload['data']['current_payment_attempt'] = $current !== null
            ? $this->presentAttempt($current, $order)['data']
            : null;

        return $orderPayload;
    }

    public function canPay(Order $order): bool
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return false;
        }
        if (! in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::PaymentProcessing], true)) {
            return false;
        }
        if ($order->reservation_expires_at !== null && $order->reservation_expires_at->lte($this->clock->now())) {
            return false;
        }

        $active = PaymentAttempt::query()
            ->where('order_id', $order->id)
            ->get()
            ->contains(fn (PaymentAttempt $attempt): bool => $attempt->status->isActive());

        if ($active && $order->status === OrderStatus::PaymentProcessing) {
            return false;
        }

        return in_array($order->payment_status, [PaymentStatus::Unpaid, PaymentStatus::Failed, PaymentStatus::Pending], true);
    }

    private function hasTerminalFailedAttempt(Order $order): bool
    {
        return PaymentAttempt::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [
                PaymentAttemptStatus::Failed->value,
                PaymentAttemptStatus::Cancelled->value,
                PaymentAttemptStatus::Expired->value,
            ])
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function action(PaymentAttempt $attempt): ?array
    {
        if ($attempt->action_type !== PaymentActionType::Redirect) {
            return $attempt->action_type === null ? null : [
                'type' => $attempt->action_type->value,
            ];
        }

        $url = $attempt->redirect_url_encrypted;
        if (! is_string($url) || $url === '') {
            return [
                'type' => PaymentActionType::None->value,
            ];
        }

        return [
            'type' => PaymentActionType::Redirect->value,
            'url' => $url,
            'method' => 'GET',
            'expires_at' => $attempt->provider_expires_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function failure(PaymentAttempt $attempt): ?array
    {
        if ($attempt->failure_category === null) {
            return null;
        }

        $category = $attempt->failure_category instanceof PaymentFailureCategory
            ? $attempt->failure_category->value
            : (string) $attempt->failure_category;

        return [
            'category' => $category,
            'code' => $attempt->failure_code,
            'message_key' => 'payment.failure.'.$category,
        ];
    }
}
