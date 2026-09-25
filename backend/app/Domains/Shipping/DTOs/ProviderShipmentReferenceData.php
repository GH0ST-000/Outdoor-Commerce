<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

final readonly class ProviderShipmentReferenceData
{
    public function __construct(
        public string $providerShipmentId,
        public string $shipmentPublicId,
    ) {}
}
