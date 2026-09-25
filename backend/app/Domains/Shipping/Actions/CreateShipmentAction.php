<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Actions;

use App\Domains\Shipping\DTOs\CreateShipmentData;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Services\CreateShipmentService;

final class CreateShipmentAction
{
    public function __construct(private readonly CreateShipmentService $service) {}

    public function execute(CreateShipmentData $data): Shipment
    {
        return $this->service->execute($data);
    }
}
