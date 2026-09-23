<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Enums\OrderActorType;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\OrderStatusReasonCode;
use App\Domains\Orders\Exceptions\OrderException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Orders\Support\OrderLogger;
use App\Domains\Shared\Support\Clock;

final class OrderStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending_payment' => ['payment_processing', 'cancelled', 'expired', 'manual_review'],
        'payment_processing' => ['confirmed', 'pending_payment', 'cancelled', 'manual_review', 'expired'],
        'manual_review' => ['confirmed', 'cancelled'],
        'confirmed' => [],
        'cancelled' => ['manual_review'],
        'expired' => ['manual_review'],
    ];

    public function __construct(
        private readonly Clock $clock,
        private readonly OrderLogger $logger,
    ) {}

    public function canTransition(?OrderStatus $from, OrderStatus $to): bool
    {
        if ($from === null) {
            return $to === OrderStatus::PendingPayment || $to === OrderStatus::ManualReview;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordInitial(
        Order $order,
        OrderStatusReasonCode $reason,
        OrderActorType $actorType,
        ?int $actorId = null,
        ?array $metadata = null,
    ): void {
        $this->append($order, null, $order->status, $reason, $actorType, $actorId, $metadata);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function transition(
        Order $order,
        OrderStatus $to,
        OrderStatusReasonCode $reason,
        OrderActorType $actorType,
        ?int $actorId = null,
        ?array $metadata = null,
    ): void {
        $from = $order->status;
        if ($from === $to) {
            return;
        }

        if (! $this->canTransition($from, $to)) {
            $this->logger->warning('state_transition_rejected', [
                'order_public_id' => $order->public_id,
                'from' => $from->value,
                'to' => $to->value,
                'reason_code' => $reason->value,
            ]);
            throw OrderException::invalidTransition($from->value, $to->value);
        }

        $now = $this->clock->now();
        $order->status = $to;

        if ($to === OrderStatus::Cancelled) {
            $order->cancelled_at = $now;
        }
        if ($to === OrderStatus::Expired) {
            $order->expired_at = $now;
        }
        if ($to === OrderStatus::Confirmed) {
            $order->confirmed_at = $now;
        }

        $order->version = $order->version + 1;
        $order->save();

        $this->append($order, $from, $to, $reason, $actorType, $actorId, $metadata);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function append(
        Order $order,
        ?OrderStatus $from,
        OrderStatus $to,
        OrderStatusReasonCode $reason,
        OrderActorType $actorType,
        ?int $actorId,
        ?array $metadata,
    ): void {
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason_code' => $reason,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => $metadata,
            'created_at' => $this->clock->now(),
        ]);
    }
}
