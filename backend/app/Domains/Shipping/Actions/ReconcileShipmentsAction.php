<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Actions;

use App\Domains\Shipping\Services\ReconcileShipmentsService;

final class ReconcileShipmentsAction
{
    public function __construct(private readonly ReconcileShipmentsService $service) {}

    /**
     * @return array{processed: int, skipped: int, failed: int}
     */
    public function execute(?string $shipmentPublicId = null, ?string $provider = null): array
    {
        return $this->service->execute($shipmentPublicId, $provider);
    }
}
