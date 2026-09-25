<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Actions;

use App\Domains\Shipping\Models\ShipmentWebhook;
use App\Domains\Shipping\Services\ReceiveShipmentWebhookService;

final class ReceiveShipmentWebhookAction
{
    public function __construct(private readonly ReceiveShipmentWebhookService $service) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function execute(string $providerCode, string $rawBody, array $headers, string $contentType): ShipmentWebhook
    {
        return $this->service->execute($providerCode, $rawBody, $headers, $contentType);
    }
}
