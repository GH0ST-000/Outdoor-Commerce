<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use Database\Factories\PickupLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property array<string, string> $name_translations
 * @property array<string, string> $address_translations
 * @property string|null $phone
 * @property array<string, string>|null $working_hours_translations
 * @property array<string, string>|null $instructions_translations
 * @property string|null $latitude
 * @property string|null $longitude
 * @property bool $is_active
 * @property int $sort_order
 */
class PickupLocation extends Model
{
    /** @use HasFactory<PickupLocationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'code',
        'name_translations',
        'address_translations',
        'phone',
        'working_hours_translations',
        'instructions_translations',
        'latitude',
        'longitude',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'address_translations' => 'array',
            'working_hours_translations' => 'array',
            'instructions_translations' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function localizedName(string $locale): string
    {
        return $this->localized($this->name_translations, $locale);
    }

    public function localizedAddress(string $locale): string
    {
        return $this->localized($this->address_translations, $locale);
    }

    /**
     * @return HasMany<DeliveryZone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    /**
     * @param  array<string, string>|null  $translations
     */
    private function localized(?array $translations, string $locale): string
    {
        if ($translations === null) {
            return '';
        }

        $value = $translations[$locale] ?? $translations['ka'] ?? $translations['en'] ?? '';

        return is_string($value) ? $value : '';
    }

    protected static function newFactory(): PickupLocationFactory
    {
        return PickupLocationFactory::new();
    }
}
