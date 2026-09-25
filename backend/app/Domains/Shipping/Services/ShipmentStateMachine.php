<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Shipping\Enums\FulfillmentType;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Exceptions\ShipmentException;
use App\Domains\Shipping\Support\ShipmentLogger;

final class ShipmentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const DELIVERY = [
        'draft' => ['preparing', 'cancelled'],
        'preparing' => ['ready_for_dispatch', 'cancelled'],
        'ready_for_dispatch' => ['shipped', 'cancelled'],
        'shipped' => ['in_transit', 'delivered', 'exception'],
        'in_transit' => ['out_for_delivery', 'delivered', 'exception'],
        'out_for_delivery' => ['delivered', 'delivery_attempt_failed', 'exception'],
        'delivery_attempt_failed' => ['in_transit', 'out_for_delivery', 'exception'],
        'exception' => ['in_transit', 'out_for_delivery', 'delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const PICKUP = [
        'draft' => ['preparing', 'cancelled'],
        'preparing' => ['ready_for_pickup', 'cancelled'],
        'ready_for_pickup' => ['collected', 'exception'],
        'exception' => ['ready_for_pickup', 'collected'],
        'collected' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly ShipmentLogger $logger) {}

    public function canTransition(FulfillmentType $type, ShipmentStatus $from, ShipmentStatus $to): bool
    {
        $map = $type === FulfillmentType::StorePickup ? self::PICKUP : self::DELIVERY;

        if ($from === $to) {
            return array_key_exists($from->value, $map);
        }

        return in_array($to->value, $map[$from->value] ?? [], true);
    }

    public function assertTransition(FulfillmentType $type, ShipmentStatus $from, ShipmentStatus $to): void
    {
        if ($this->canTransition($type, $from, $to)) {
            return;
        }

        $this->logger->warning('state_transition_rejected', [
            'from' => $from->value,
            'to' => $to->value,
            'fulfillment_type' => $type->value,
        ]);

        throw ShipmentException::invalidTransition($from->value, $to->value);
    }

    public function assertStatusAllowedForType(FulfillmentType $type, ShipmentStatus $status): void
    {
        if ($type === FulfillmentType::StorePickup && $status->isDeliveryStatus()) {
            throw ShipmentException::invalidTransition($status->value, $status->value);
        }

        if ($type === FulfillmentType::Delivery && $status->isPickupStatus()) {
            throw ShipmentException::invalidTransition($status->value, $status->value);
        }
    }
}
