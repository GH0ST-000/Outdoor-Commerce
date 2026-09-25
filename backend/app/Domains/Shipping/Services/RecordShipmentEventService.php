<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Shared\Support\Clock;
use App\Domains\Shipping\Enums\ShipmentEventCode;
use App\Domains\Shipping\Enums\ShipmentEventSource;
use App\Domains\Shipping\Enums\ShipmentStatus;
use App\Domains\Shipping\Models\Shipment;
use App\Domains\Shipping\Models\ShipmentEvent;
use Illuminate\Support\Str;

final class RecordShipmentEventService
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * @param  array<string, mixed>|null  $safeMetadata
     */
    public function record(
        Shipment $shipment,
        ShipmentStatus $status,
        ShipmentEventCode $code,
        ShipmentEventSource $source,
        ?string $customerMessageKey = null,
        ?string $internalMessage = null,
        ?string $locationLabel = null,
        ?string $providerEventId = null,
        ?array $safeMetadata = null,
        ?int $actorUserId = null,
    ): ShipmentEvent {
        return ShipmentEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'shipment_id' => $shipment->id,
            'status' => $status,
            'event_code' => $code,
            'source' => $source,
            'occurred_at' => $this->clock->now(),
            'location_label' => $locationLabel,
            'customer_message_key' => $customerMessageKey,
            'internal_message' => $internalMessage,
            'provider_event_id' => $providerEventId,
            'safe_metadata' => $safeMetadata,
            'created_by' => $actorUserId,
            'created_at' => $this->clock->now(),
        ]);
    }
}
