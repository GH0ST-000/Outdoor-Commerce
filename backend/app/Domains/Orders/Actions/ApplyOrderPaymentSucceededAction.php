<?php

declare(strict_types=1);

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Enums\OrderActorType;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\OrderStatusReasonCode;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Events\OrderConfirmed;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\OrderPaymentStatusService;
use App\Domains\Orders\Services\OrderStateMachine;
use Illuminate\Support\Facades\DB;

final class ApplyOrderPaymentSucceededAction
{
    public function __construct(
        private readonly OrderStateMachine $states,
        private readonly OrderPaymentStatusService $payments,
    ) {}

    public function execute(Order $order): void
    {
        $this->payments->transition($order, PaymentStatus::Paid);

        if ($order->status === OrderStatus::PaymentProcessing || $order->status === OrderStatus::PendingPayment) {
            $this->states->transition(
                $order,
                OrderStatus::Confirmed,
                OrderStatusReasonCode::PaymentConfirmed,
                OrderActorType::System,
            );
        }

        $publicId = $order->public_id;
        $number = $order->order_number;
        DB::afterCommit(function () use ($publicId, $number): void {
            event(new OrderConfirmed($publicId, $number));
        });
    }
}
