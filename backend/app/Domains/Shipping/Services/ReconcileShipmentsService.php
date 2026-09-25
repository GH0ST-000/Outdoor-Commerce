<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Shared\Support\Clock;
use App\Domains\Shipping\DTOs\ProviderShipmentReferenceData;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Support\ShipmentLogger;

final class ReconcileShipmentsService
{
    public function __construct(
        private readonly ShipmentProviderRegistry $providers,
        private readonly Clock $clock,
        private readonly ShipmentLogger $logger,
    ) {}

    /**
     * @return array{processed: int, skipped: int, failed: int}
     */
    public function execute(?string $shipmentPublicId = null, ?string $provider = null): array
    {
        $query = Shipment::query()
            ->whereNotIn('status', [
                ShipmentStatus::Delivered->value,
                ShipmentStatus::Collected->value,
                ShipmentStatus::Cancelled->value,
                ShipmentStatus::Draft->value,
            ])
            ->orderBy('id')
            ->limit(max(1, (int) config('shipping.reconcile_chunk_size', 50)));

        if ($shipmentPublicId !== null) {
            $query->where('public_id', $shipmentPublicId);
        }
        if ($provider !== null) {
            $query->where('provider_code', $provider);
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->get() as $shipment) {
            if ($shipment->provider_code === 'manual') {
                $skipped++;

                continue;
            }
            try {
                $adapter = $this->providers->resolve($shipment->provider_code);
                if (! $adapter->claimsSynchronization() || $shipment->provider_shipment_id === null) {
                    $skipped++;

                    continue;
                }
                $adapter->fetchShipmentStatus(new ProviderShipmentReferenceData(
                    $shipment->provider_shipment_id,
                    $shipment->public_id,
                ));
                $shipment->last_provider_sync_at = $this->clock->now();
                $shipment->save();
                $processed++;
            } catch (\Throwable) {
                $failed++;
                $this->logger->warning('reconcile_failed', [
                    'shipment_public_id' => $shipment->public_id,
                    'provider' => $shipment->provider_code,
                ]);
            }
        }

        return ['processed' => $processed, 'skipped' => $skipped, 'failed' => $failed];
    }
}
