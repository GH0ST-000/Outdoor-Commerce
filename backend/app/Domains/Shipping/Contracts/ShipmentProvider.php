<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Contracts;

use App\Domains\Shipping\DTOs\CancelProviderShipmentResultData;
use App\Domains\Shipping\DTOs\CreateProviderShipmentRequestData;
use App\Domains\Shipping\DTOs\CreateProviderShipmentResultData;
use App\Domains\Shipping\DTOs\ProviderShipmentReferenceData;
use App\Domains\Shipping\DTOs\ProviderShipmentStatusResultData;
use App\Domains\Shipping\DTOs\ProviderShipmentWebhookRequestData;
use App\Domains\Shipping\DTOs\VerifiedProviderShipmentWebhookData;

interface ShipmentProvider
{
    public function code(): string;

    public function claimsSynchronization(): bool;

    public function createShipment(
        CreateProviderShipmentRequestData $request,
    ): CreateProviderShipmentResultData;

    public function fetchShipmentStatus(
        ProviderShipmentReferenceData $reference,
    ): ProviderShipmentStatusResultData;

    public function cancelShipment(
        ProviderShipmentReferenceData $reference,
    ): CancelProviderShipmentResultData;

    public function parseAndVerifyWebhook(
        ProviderShipmentWebhookRequestData $request,
    ): VerifiedProviderShipmentWebhookData;
}
