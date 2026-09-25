<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

final readonly class VerifiedProviderShipmentWebhookData
{
    /**
     * @param  array<string, mixed>  $safePayload
     */
    public function __construct(
        public string $providerEventId,
        public string $providerShipmentId,
        public string $providerStatus,
        public string $normalizedStatus,
        public array $safePayload,
        public bool $unknownMappedToException,
    ) {}
}
