<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use App\Domains\Checkout\Enums\FulfillmentMethodType;
use Database\Factories\FulfillmentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property FulfillmentMethodType $type
 * @property string $code
 * @property array<string, string> $name_translations
 * @property array<string, string>|null $description_translations
 * @property bool $is_active
 * @property string $currency
 * @property int $base_price_minor
 * @property int|null $free_above_minor
 * @property int|null $estimated_min_days
 * @property int|null $estimated_max_days
 * @property int $sort_order
 * @property bool $requires_address
 * @property array<string, mixed>|null $configuration
 */
class FulfillmentMethod extends Model
{
    /** @use HasFactory<FulfillmentMethodFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'type',
        'code',
        'name_translations',
        'description_translations',
        'is_active',
        'currency',
        'base_price_minor',
        'free_above_minor',
        'estimated_min_days',
        'estimated_max_days',
        'sort_order',
        'requires_address',
        'configuration',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FulfillmentMethodType::class,
            'name_translations' => 'array',
            'description_translations' => 'array',
            'is_active' => 'boolean',
            'base_price_minor' => 'integer',
            'free_above_minor' => 'integer',
            'estimated_min_days' => 'integer',
            'estimated_max_days' => 'integer',
            'sort_order' => 'integer',
            'requires_address' => 'boolean',
            'configuration' => 'array',
        ];
    }

    public function localizedName(string $locale): string
    {
        return $this->localized($this->name_translations, $locale);
    }

    public function localizedDescription(string $locale): ?string
    {
        $value = $this->localized($this->description_translations, $locale);

        return $value === '' ? null : $value;
    }

    /**
     * @return HasMany<DeliveryRateRule, $this>
     */
    public function rateRules(): HasMany
    {
        return $this->hasMany(DeliveryRateRule::class);
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

    protected static function newFactory(): FulfillmentMethodFactory
    {
        return FulfillmentMethodFactory::new();
    }
}
