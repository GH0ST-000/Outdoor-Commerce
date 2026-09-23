<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DeliveryRateRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fulfillment_method_id
 * @property int $delivery_zone_id
 * @property int|null $minimum_subtotal_minor
 * @property int|null $maximum_subtotal_minor
 * @property int $price_minor
 * @property int|null $free_above_minor
 * @property int $priority
 * @property bool $is_active
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 */
class DeliveryRateRule extends Model
{
    /** @use HasFactory<DeliveryRateRuleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fulfillment_method_id',
        'delivery_zone_id',
        'minimum_subtotal_minor',
        'maximum_subtotal_minor',
        'price_minor',
        'free_above_minor',
        'priority',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_subtotal_minor' => 'integer',
            'maximum_subtotal_minor' => 'integer',
            'price_minor' => 'integer',
            'free_above_minor' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<FulfillmentMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(FulfillmentMethod::class, 'fulfillment_method_id');
    }

    /**
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    protected static function newFactory(): DeliveryRateRuleFactory
    {
        return DeliveryRateRuleFactory::new();
    }
}
