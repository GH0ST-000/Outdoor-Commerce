<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use Database\Factories\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property array<string, string> $name_translations
 * @property string $country_code
 * @property string|null $region
 * @property string|null $municipality_or_city
 * @property string|null $postal_code_pattern
 * @property int|null $pickup_location_id
 * @property bool $is_active
 * @property int $priority
 */
class DeliveryZone extends Model
{
    /** @use HasFactory<DeliveryZoneFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'code',
        'name_translations',
        'country_code',
        'region',
        'municipality_or_city',
        'postal_code_pattern',
        'pickup_location_id',
        'is_active',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PickupLocation, $this>
     */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(PickupLocation::class);
    }

    /**
     * @return HasMany<DeliveryRateRule, $this>
     */
    public function rateRules(): HasMany
    {
        return $this->hasMany(DeliveryRateRule::class);
    }

    protected static function newFactory(): DeliveryZoneFactory
    {
        return DeliveryZoneFactory::new();
    }
}
