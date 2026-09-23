<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Exceptions\OrderException;
use App\Domains\Orders\Models\Order;

/**
 * Payment-status transitions live here so Day 20 can attach provider attempts
 * without mutating order status ad hoc. Day 19 only initializes unpaid and
 * marks cancelled/expired unpaid orders.
 */
final class OrderPaymentStatusService
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'unpaid' => ['pending', 'cancelled', 'failed'],
        'pending' => ['paid', 'failed', 'cancelled', 'unpaid'],
        'paid' => ['partially_refunded', 'refunded'],
        'failed' => ['pending', 'cancelled', 'unpaid'],
        'cancelled' => [],
        'partially_refunded' => ['refunded'],
        'refunded' => [],
    ];

    public function canTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(Order $order, PaymentStatus $to): void
    {
        $from = $order->payment_status;
        if ($from === $to) {
            return;
        }

        if (! $this->canTransition($from, $to)) {
            throw OrderException::invalidTransition($from->value, $to->value);
        }

        $order->payment_status = $to;
        if ($to === PaymentStatus::Paid) {
            $order->paid_at = $order->paid_at ?? now()->toImmutable();
        }
        $order->save();
    }
}
