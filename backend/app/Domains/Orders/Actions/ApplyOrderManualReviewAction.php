<?php

declare(strict_types=1);

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Enums\OrderActorType;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\OrderStatusReasonCode;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\OrderStateMachine;

final class ApplyOrderManualReviewAction
{
    public function __construct(private readonly OrderStateMachine $states) {}

    public function execute(Order $order, OrderStatusReasonCode $reason): void
    {
        if ($order->status === OrderStatus::ManualReview) {
            return;
        }

        $this->states->transition(
            $order,
            OrderStatus::ManualReview,
            $reason,
            OrderActorType::System,
            null,
            ['reason_code' => $reason->value],
        );
    }
}
