<?php

declare(strict_types=1);

namespace App\Domains\Shipping\DTOs;

final readonly class ProviderShipmentWebhookRequestData
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $rawBody,
        public array $headers,
        public string $contentType,
    ) {}
}
