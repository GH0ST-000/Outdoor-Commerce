<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Orders\Models\Order;
use App\Domains\Shipping\Enums\FulfillmentType;
use App\Domains\Shipping\Enums\ShipmentExceptionCode;
use App\Domains\Shipping\Enums\ShipmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical dispatch or store-pickup batch. Inventory is not deducted here.
 *
 * @property int $id
 * @property string $public_id
 * @property string $shipment_number
 * @property int $order_id
 * @property int|null $warehouse_id
 * @property FulfillmentType $fulfillment_type
 * @property string $provider_code
 * @property string|null $service_code
 * @property ShipmentStatus $status
 * @property string|null $carrier_display_name
 * @property string|null $tracking_number
 * @property string|null $public_tracking_url
 * @property string|null $provider_shipment_id
 * @property string|null $pickup_location_public_id
 * @property array<string, mixed> $recipient_snapshot
 * @property array<string, mixed>|null $address_snapshot
 * @property array<string, mixed>|null $pickup_location_snapshot
 * @property int $package_count
 * @property int|null $weight_grams
 * @property int|null $length_mm
 * @property int|null $width_mm
 * @property int|null $height_mm
 * @property CarbonImmutable|null $estimated_delivery_from
 * @property CarbonImmutable|null $estimated_delivery_to
 * @property bool $estimated_delivery_is_guaranteed
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $collected_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $last_provider_sync_at
 * @property ShipmentExceptionCode|null $exception_code
 * @property int $version
 * @property int|null $created_by
 */
class Shipment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'shipment_number',
        'order_id',
        'warehouse_id',
        'fulfillment_type',
        'provider_code',
        'service_code',
        'status',
        'carrier_display_name',
        'tracking_number',
        'public_tracking_url',
        'provider_shipment_id',
        'pickup_location_public_id',
        'recipient_snapshot',
        'address_snapshot',
        'pickup_location_snapshot',
        'package_count',
        'weight_grams',
        'length_mm',
        'width_mm',
        'height_mm',
        'estimated_delivery_from',
        'estimated_delivery_to',
        'estimated_delivery_is_guaranteed',
        'shipped_at',
        'delivered_at',
        'collected_at',
        'cancelled_at',
        'last_provider_sync_at',
        'exception_code',
        'version',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fulfillment_type' => FulfillmentType::class,
            'status' => ShipmentStatus::class,
            'exception_code' => ShipmentExceptionCode::class,
            'recipient_snapshot' => 'encrypted:array',
            'address_snapshot' => 'encrypted:array',
            'pickup_location_snapshot' => 'array',
            'package_count' => 'integer',
            'weight_grams' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'estimated_delivery_is_guaranteed' => 'boolean',
            'estimated_delivery_from' => 'immutable_datetime',
            'estimated_delivery_to' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'collected_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'last_provider_sync_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<ShipmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * @return HasMany<ShipmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
