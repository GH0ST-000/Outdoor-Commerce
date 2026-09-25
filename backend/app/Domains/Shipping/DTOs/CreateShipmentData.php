<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

final readonly class CreateShipmentData
{
    /**
     * @param  list<ShipmentItemQuantityData>  $items
     */
    public function __construct(
        public string $orderPublicId,
        public array $items,
        public string $providerCode,
        public ?string $warehouseCode,
        public ?string $fulfillmentType,
        public ?string $carrierDisplayName,
        public ?string $trackingNumber,
        public ?string $trackingUrl,
        public ?int $packageCount,
        public ?string $internalNote,
        public ?string $idempotencyKey,
        public int $actorUserId,
        public ?string $requestId,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
