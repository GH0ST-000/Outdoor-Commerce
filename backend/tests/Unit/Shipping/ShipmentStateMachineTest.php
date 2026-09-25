<?php

declare(strict_types=1);

use App\Domains\Shipping\Enums\FulfillmentType;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Services\ShipmentStateMachine;
use App\Domains\Shipping\Support\ShipmentLogger;

it('allows documented delivery and pickup transitions and rejects the rest', function (): void {
    $machine = new ShipmentStateMachine(new ShipmentLogger);

    $deliveryAllowed = [
        'draft' => ['draft', 'preparing', 'cancelled'],
        'preparing' => ['preparing', 'ready_for_dispatch', 'cancelled'],
        'ready_for_dispatch' => ['ready_for_dispatch', 'shipped', 'cancelled'],
        'shipped' => ['shipped', 'in_transit', 'delivered', 'exception'],
        'in_transit' => ['in_transit', 'out_for_delivery', 'delivered', 'exception'],
        'out_for_delivery' => ['out_for_delivery', 'delivered', 'delivery_attempt_failed', 'exception'],
        'delivery_attempt_failed' => ['delivery_attempt_failed', 'in_transit', 'out_for_delivery', 'exception'],
        'exception' => ['exception', 'in_transit', 'out_for_delivery', 'delivered'],
        'delivered' => ['delivered'],
        'cancelled' => ['cancelled'],
    ];

    foreach (ShipmentStatus::cases() as $from) {
        foreach (ShipmentStatus::cases() as $to) {
            $expected = in_array($to->value, $deliveryAllowed[$from->value] ?? [], true);
            expect($machine->canTransition(FulfillmentType::Delivery, $from, $to))->toBe($expected);
        }
    }

    expect($machine->canTransition(FulfillmentType::StorePickup, ShipmentStatus::Preparing, ShipmentStatus::ReadyForPickup))->toBeTrue()
        ->and($machine->canTransition(FulfillmentType::StorePickup, ShipmentStatus::Collected, ShipmentStatus::ReadyForPickup))->toBeFalse()
        ->and($machine->canTransition(FulfillmentType::StorePickup, ShipmentStatus::Draft, ShipmentStatus::Shipped))->toBeFalse()
        ->and($machine->canTransition(FulfillmentType::Delivery, ShipmentStatus::Draft, ShipmentStatus::ReadyForPickup))->toBeFalse();
});
