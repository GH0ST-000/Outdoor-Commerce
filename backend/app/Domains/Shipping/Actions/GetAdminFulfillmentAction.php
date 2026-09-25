<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Actions;

use App\Domains\Orders\Models\Order;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Services\GetShipmentService;

final class GetAdminFulfillmentAction
{
    public function __construct(private readonly GetShipmentService $shipments) {}

    public function order(string $orderPublicId): Order
    {
        return $this->shipments->orderByPublicId($orderPublicId);
    }

    public function shipment(string $shipmentPublicId): Shipment
    {
        return $this->shipments->byPublicId($shipmentPublicId);
    }
}
