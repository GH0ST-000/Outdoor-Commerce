<?php

declare(strict_types=1);

use App\Domains\Orders\Enums\OrderActorType;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\OrderStatusReasonCode;
use App\Domains\Orders\Exceptions\OrderException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\OrderStateMachine;
use App\Domains\Orders\Support\OrderLogger;
use App\Domains\Shared\Support\SystemClock;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows the pending-payment transitions required for Day 19 and Day 20', function (): void {
    $machine = new OrderStateMachine(new SystemClock, new OrderLogger);

    expect($machine->canTransition(OrderStatus::PendingPayment, OrderStatus::PaymentProcessing))->toBeTrue()
        ->and($machine->canTransition(OrderStatus::PendingPayment, OrderStatus::Cancelled))->toBeTrue()
        ->and($machine->canTransition(OrderStatus::PendingPayment, OrderStatus::Expired))->toBeTrue()
        ->and($machine->canTransition(OrderStatus::PaymentProcessing, OrderStatus::Confirmed))->toBeTrue()
        ->and($machine->canTransition(OrderStatus::PaymentProcessing, OrderStatus::PendingPayment))->toBeTrue()
        ->and($machine->canTransition(OrderStatus::ManualReview, OrderStatus::Confirmed))->toBeTrue()
        ->and($machine->canTransition(OrderStatus::Confirmed, OrderStatus::Cancelled))->toBeFalse()
        ->and($machine->canTransition(OrderStatus::Cancelled, OrderStatus::PendingPayment))->toBeFalse();
});

it('records history and rejects illegal transitions', function (): void {
    $order = Order::factory()->create();
    $machine = app(OrderStateMachine::class);
    $machine->recordInitial($order, OrderStatusReasonCode::OrderCreated, OrderActorType::System);
    $machine->transition(
        $order,
        OrderStatus::Cancelled,
        OrderStatusReasonCode::CustomerCancelled,
        OrderActorType::Customer,
    );

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled)
        ->and($order->statusHistory()->count())->toBe(2);

    expect(fn () => $machine->transition(
        $order,
        OrderStatus::Confirmed,
        OrderStatusReasonCode::PaymentConfirmed,
        OrderActorType::System,
    ))->toThrow(OrderException::class);
});
