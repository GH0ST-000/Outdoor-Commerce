<?php

declare(strict_types=1);

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Enums\OrderActorType;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\OrderStatusReasonCode;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\OrderPaymentStatusService;
use App\Domains\Orders\Services\OrderStateMachine;

final class ApplyOrderPaymentStartedAction
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly OrderPaymentStatusService $payments,
    ) {}

    public function execute(Order $order): void
    {
        if ($order->status === OrderStatus::PendingPayment) {
            $this->states->transition(
                $order,
                OrderStatus::PaymentProcessing,
                OrderStatusReasonCode::PaymentStarted,
                OrderActorType::System,
            );
        }

        if ($order->payment_status === PaymentStatus::Unpaid || $order->payment_status === PaymentStatus::Failed) {
            $this->payments->transition($order, PaymentStatus::Pending);
        }
    }
}
