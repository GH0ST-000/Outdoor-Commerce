<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Shared\Support\Clock;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Support\ShipmentLogger;

final class DetectStaleShipmentsService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly ShipmentLogger $logger,
    ) {}

    /**
     * @return list<array{id: string, number: string, status: string, reason: string}>
     */
    public function execute(): array
    {
        $now = $this->clock->now();
        $thresholds = [
            ShipmentStatus::Preparing->value => (int) config('shipping.stale.preparing_minutes', 1440),
            ShipmentStatus::ReadyForDispatch->value => (int) config('shipping.stale.ready_for_dispatch_minutes', 720),
            ShipmentStatus::Shipped->value => (int) config('shipping.stale.shipped_minutes', 10080),
            ShipmentStatus::InTransit->value => (int) config('shipping.stale.shipped_minutes', 10080),
            ShipmentStatus::Exception->value => (int) config('shipping.stale.exception_minutes', 2880),
            ShipmentStatus::ReadyForPickup->value => (int) config('shipping.stale.ready_for_pickup_minutes', 10080),
        ];

        $stale = [];
        $rows = Shipment::query()
            ->whereIn('status', array_keys($thresholds))
            ->orderBy('id')
            ->get();

        foreach ($rows as $shipment) {
            $minutes = $thresholds[$shipment->status->value] ?? 0;
            $cutoff = $now->subMinutes($minutes);
            if ($shipment->updated_at === null || $shipment->updated_at->gt($cutoff)) {
                continue;
            }
            $reason = match ($shipment->status) {
                ShipmentStatus::Preparing => 'preparing_too_long',
                ShipmentStatus::ReadyForDispatch => 'ready_for_dispatch_too_long',
                ShipmentStatus::Shipped, ShipmentStatus::InTransit => 'shipped_without_updates',
                ShipmentStatus::Exception => 'exception_unresolved',
                ShipmentStatus::ReadyForPickup => 'pickup_ready_too_long',
                default => 'stale',
            };
            $stale[] = [
                'id' => $shipment->public_id,
                'number' => $shipment->shipment_number,
                'status' => $shipment->status->value,
                'reason' => $reason,
            ];
            $this->logger->warning('stale_detected', [
                'shipment_public_id' => $shipment->public_id,
                'reason' => $reason,
            ]);
        }

        return $stale;
    }
}
