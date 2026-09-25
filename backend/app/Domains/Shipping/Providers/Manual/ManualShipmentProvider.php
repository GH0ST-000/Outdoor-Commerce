<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Providers\Manual;

use App\Domains\Shipping\Contracts\ShipmentProvider;
use App\Domains\Shipping\DTOs\CancelProviderShipmentResultData;
use App\Domains\Shipping\DTOs\CreateProviderShipmentRequestData;
use App\Domains\Shipping\DTOs\CreateProviderShipmentResultData;
use App\Domains\Shipping\DTOs\ProviderShipmentReferenceData;
use App\Domains\Shipping\DTOs\ProviderShipmentStatusResultData;
use App\Domains\Shipping\DTOs\ProviderShipmentWebhookRequestData;
use App\Domains\Shipping\DTOs\VerifiedProviderShipmentWebhookData;
use App\Domains\Shipping\Enums\ShipmentProviderCode;
use App\Domains\Shipping\Exceptions\ShipmentException;
use App\Domains\Shipping\Support\TrackingUrlValidator;

final class ManualShipmentProvider implements ShipmentProvider
{
    public function __construct(private readonly TrackingUrlValidator $trackingUrls) {}

    public function code(): string
    {
        return ShipmentProviderCode::Manual->value;
    }

    public function claimsSynchronization(): bool
    {
        return false;
    }

    public function createShipment(CreateProviderShipmentRequestData $request): CreateProviderShipmentResultData
    {
        $url = $this->trackingUrls->validate($request->trackingUrl);

        return new CreateProviderShipmentResultData(
            providerShipmentId: $request->shipmentPublicId,
            claimsSynchronization: false,
            trackingNumber: $request->trackingNumber,
            trackingUrl: $url,
            carrierDisplayName: $request->carrierDisplayName,
        );
    }

    public function fetchShipmentStatus(ProviderShipmentReferenceData $reference): ProviderShipmentStatusResultData
    {
        throw ShipmentException::providerUnavailable();
    }

    public function cancelShipment(ProviderShipmentReferenceData $reference): CancelProviderShipmentResultData
    {
        return new CancelProviderShipmentResultData(cancelled: true, supported: true);
    }

    public function parseAndVerifyWebhook(ProviderShipmentWebhookRequestData $request): VerifiedProviderShipmentWebhookData
    {
        throw ShipmentException::providerUnavailable();
    }
}
