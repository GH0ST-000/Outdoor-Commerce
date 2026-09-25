<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Shared\Support\Clock;
use App\Domains\Shipping\DTOs\ProviderShipmentWebhookRequestData;
use App\Domains\Shipping\DTOs\TransitionShipmentData;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Enums\ShipmentWebhookProcessingStatus;
use App\Domains\Shipping\Enums\ShipmentWebhookSignatureStatus;
use App\Domains\Shipping\Exceptions\ShipmentException;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Models\ShipmentWebhook;
use App\Domains\Shipping\Support\ShipmentLogger;
use Illuminate\Support\Str;

final class ReceiveShipmentWebhookService
{
    public function __construct(
        private readonly ShipmentProviderRegistry $providers,
        private readonly TransitionShipmentService $transitions,
        private readonly Clock $clock,
        private readonly ShipmentLogger $logger,
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function execute(string $providerCode, string $rawBody, array $headers, string $contentType): ShipmentWebhook
    {
        if ($providerCode === 'manual') {
            throw ShipmentException::providerUnavailable();
        }

        $max = max(1, (int) config('shipping.webhook_max_bytes', 65536));
        if (strlen($rawBody) > $max) {
            throw ShipmentException::payloadTooLarge();
        }

        $provider = $this->providers->resolve($providerCode);
        $hash = hash('sha256', $rawBody);

        try {
            $verified = $provider->parseAndVerifyWebhook(new ProviderShipmentWebhookRequestData(
                rawBody: $rawBody,
                headers: $headers,
                contentType: $contentType,
            ));
        } catch (ShipmentException $exception) {
            $this->logger->warning('webhook_rejected', [
                'provider' => $providerCode,
                'error_code' => $exception->errorCode(),
            ]);
            throw $exception;
        }

        $existing = ShipmentWebhook::query()
            ->where('provider', $providerCode)
            ->where('provider_event_id', $verified->providerEventId)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $row = ShipmentWebhook::query()->create([
            'public_id' => (string) Str::uuid(),
            'provider' => $providerCode,
            'provider_event_id' => $verified->providerEventId,
            'provider_shipment_id' => $verified->providerShipmentId,
            'payload_hash' => $hash,
            'signature_status' => ShipmentWebhookSignatureStatus::Verified,
            'processing_status' => ShipmentWebhookProcessingStatus::Received,
            'received_at' => $this->clock->now(),
            'safe_payload' => $verified->safePayload,
            'safe_headers' => $headers,
        ]);

        $shipment = Shipment::query()->where('provider_shipment_id', $verified->providerShipmentId)->first();
        if ($shipment === null) {
            $row->processing_status = ShipmentWebhookProcessingStatus::Unmatched;
            $row->last_error_code = 'SHIPMENT_NOT_FOUND';
            $row->save();

            return $row;
        }

        $status = ShipmentStatus::tryFrom($verified->normalizedStatus) ?? ShipmentStatus::Exception;
        if ($verified->unknownMappedToException) {
            $status = ShipmentStatus::Exception;
        }
        if ($status === ShipmentStatus::Delivered && $verified->unknownMappedToException) {
            $status = ShipmentStatus::Exception;
        }

        $this->applyNormalized($shipment, $status);
        $row->processing_status = ShipmentWebhookProcessingStatus::Processed;
        $row->processed_at = $this->clock->now();
        $row->save();

        return $row;
    }

    private function applyNormalized(Shipment $shipment, ShipmentStatus $status): void
    {
        $data = new TransitionShipmentData(
            shipmentPublicId: $shipment->public_id,
            expectedVersion: $shipment->version,
            actorUserId: null,
        );

        match ($status) {
            ShipmentStatus::InTransit => $this->transitions->markInTransit($data),
            ShipmentStatus::OutForDelivery => $this->transitions->markOutForDelivery($data),
            ShipmentStatus::Delivered => $this->transitions->markDelivered($data),
            ShipmentStatus::DeliveryAttemptFailed => $this->transitions->recordDeliveryAttemptFailed($data),
            ShipmentStatus::Exception => $this->transitions->recordException($data),
            default => null,
        };
    }
}
