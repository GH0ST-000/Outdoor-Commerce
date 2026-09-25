<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Models;

use App\Domains\Shipping\Enums\ShipmentWebhookProcessingStatus;
use App\Domains\Shipping\Enums\ShipmentWebhookSignatureStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Durable inbox for future carrier webhooks. Manual fulfillment does not emit these.
 *
 * @property int $id
 * @property string $public_id
 * @property string $provider
 * @property string|null $provider_event_id
 * @property string|null $provider_shipment_id
 * @property string $payload_hash
 * @property ShipmentWebhookSignatureStatus $signature_status
 * @property ShipmentWebhookProcessingStatus $processing_status
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $processed_at
 * @property string|null $last_error_code
 * @property array<string, mixed>|null $safe_payload
 * @property array<string, mixed>|null $safe_headers
 */
class ShipmentWebhook extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'provider',
        'provider_event_id',
        'provider_shipment_id',
        'payload_hash',
        'signature_status',
        'processing_status',
        'received_at',
        'processed_at',
        'last_error_code',
        'safe_payload',
        'safe_headers',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_status' => ShipmentWebhookSignatureStatus::class,
            'processing_status' => ShipmentWebhookProcessingStatus::class,
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'safe_payload' => 'array',
            'safe_headers' => 'array',
        ];
    }
}
