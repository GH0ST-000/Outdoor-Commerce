<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

final readonly class ProviderShipmentStatusResultData
{
    public function __construct(
        public string $providerStatus,
        public string $normalizedStatus,
        public ?string $trackingNumber = null,
        public ?string $providerEventId = null,
        public bool $unknownMappedToException = false,
    ) {}
}
