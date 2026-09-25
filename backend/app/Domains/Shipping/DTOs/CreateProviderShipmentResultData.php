<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

final readonly class CreateProviderShipmentResultData
{
    public function __construct(
        public string $providerShipmentId,
        public bool $claimsSynchronization,
        public ?string $trackingNumber = null,
        public ?string $trackingUrl = null,
        public ?string $carrierDisplayName = null,
    ) {}
}
