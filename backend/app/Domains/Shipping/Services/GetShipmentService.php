<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Orders\Models\Order;
use App\Domains\Shipping\Exceptions\ShipmentException;
use App\Domains\Shipping\Models\Shipment;

final class GetShipmentService
{
    public function byPublicId(string $publicId): Shipment
    {
        $shipment = Shipment::query()
            ->where('public_id', $publicId)
            ->with(['items.orderItem', 'events', 'warehouse', 'order'])
            ->first();
        if ($shipment === null) {
            throw ShipmentException::notFound();
        }

        return $shipment;
    }

    public function orderByPublicId(string $orderPublicId): Order
    {
        $order = Order::query()->where('public_id', $orderPublicId)->with('items')->first();
        if ($order === null) {
            throw ShipmentException::notFound();
        }

        return $order;
    }
}
