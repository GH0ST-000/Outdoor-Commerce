<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Shipping\Enums\ShipmentEventCode;
use App\Domains\Shipping\Enums\ShipmentEventSource;
use App\Domains\Shipping\Enums\ShipmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only shipment timeline event. Internal messages must never be public.
 *
 * @property int $id
 * @property string $public_id
 * @property int $shipment_id
 * @property ShipmentStatus $status
 * @property ShipmentEventCode $event_code
 * @property ShipmentEventSource $source
 * @property CarbonImmutable $occurred_at
 * @property string|null $location_label
 * @property string|null $customer_message_key
 * @property string|null $internal_message
 * @property string|null $provider_event_id
 * @property array<string, mixed>|null $safe_metadata
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 */
class ShipmentEvent extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'shipment_id',
        'status',
        'event_code',
        'source',
        'occurred_at',
        'location_label',
        'customer_message_key',
        'internal_message',
        'provider_event_id',
        'safe_metadata',
        'created_by',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'event_code' => ShipmentEventCode::class,
            'source' => ShipmentEventSource::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'safe_metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
