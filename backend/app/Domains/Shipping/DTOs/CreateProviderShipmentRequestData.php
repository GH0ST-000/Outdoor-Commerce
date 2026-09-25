<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

final readonly class CreateProviderShipmentRequestData
{
    /**
     * @param  list<array{sku: string, name: string, quantity: int}>  $items
     */
    public function __construct(
        public string $shipmentPublicId,
        public string $shipmentNumber,
        public string $orderPublicId,
        public string $fulfillmentType,
        public array $items,
        public ?string $trackingNumber,
        public ?string $trackingUrl,
        public ?string $carrierDisplayName,
    ) {}
}
