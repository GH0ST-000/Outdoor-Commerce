<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

use App\Domains\Shipping\Enums\ShipmentExceptionCode;

final readonly class TransitionShipmentData
{
    /**
     * @param  list<ShipmentItemQuantityData>|null  $items
     */
    public function __construct(
        public string $shipmentPublicId,
        public int $expectedVersion,
        public ?int $actorUserId,
        public ?string $internalNote = null,
        public ?array $items = null,
        public ?string $trackingNumber = null,
        public ?string $trackingUrl = null,
        public ?string $carrierDisplayName = null,
        public ?ShipmentExceptionCode $exceptionCode = null,
        public ?string $customerLocationLabel = null,
        public ?string $idempotencyKey = null,
        public ?string $requestId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $endpoint = null,
    ) {}
}
