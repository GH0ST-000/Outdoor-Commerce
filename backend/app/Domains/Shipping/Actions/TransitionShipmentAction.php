<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Actions;

use App\Domains\Shipping\DTOs\TransitionShipmentData;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Services\TransitionShipmentService;

final class TransitionShipmentAction
{
    public function __construct(private readonly TransitionShipmentService $service) {}

    public function startPreparation(TransitionShipmentData $data): Shipment
    {
        return $this->service->startPreparation($data);
    }

    public function recordPicked(TransitionShipmentData $data): Shipment
    {
        return $this->service->recordPicked($data);
    }

    public function recordPacked(TransitionShipmentData $data): Shipment
    {
        return $this->service->recordPacked($data);
    }

    public function markReadyForDispatch(TransitionShipmentData $data): Shipment
    {
        return $this->service->markReadyForDispatch($data);
    }

    public function dispatch(TransitionShipmentData $data): Shipment
    {
        return $this->service->dispatch($data);
    }

    public function markInTransit(TransitionShipmentData $data): Shipment
    {
        return $this->service->markInTransit($data);
    }

    public function markOutForDelivery(TransitionShipmentData $data): Shipment
    {
        return $this->service->markOutForDelivery($data);
    }

    public function markDelivered(TransitionShipmentData $data): Shipment
    {
        return $this->service->markDelivered($data);
    }

    public function recordDeliveryAttemptFailed(TransitionShipmentData $data): Shipment
    {
        return $this->service->recordDeliveryAttemptFailed($data);
    }

    public function markReadyForPickup(TransitionShipmentData $data): Shipment
    {
        return $this->service->markReadyForPickup($data);
    }

    public function markCollected(TransitionShipmentData $data): Shipment
    {
        return $this->service->markCollected($data);
    }

    public function recordException(TransitionShipmentData $data): Shipment
    {
        return $this->service->recordException($data);
    }

    public function cancel(TransitionShipmentData $data): Shipment
    {
        return $this->service->cancel($data);
    }
}
