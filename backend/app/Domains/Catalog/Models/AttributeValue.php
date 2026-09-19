<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\AttributeValueStatus;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Models\User;
use Database\Factories\AttributeValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $attribute_id
 * @property string $code
 * @property AttributeValueStatus $status
 * @property int $sort_order
 * @property string|null $color_hex
 * @property array<string, mixed>|null $metadata
 */
class AttributeValue extends Model
{
    /** @use HasFactory<AttributeValueFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'attribute_id',
        'code',
        'status',
        'sort_order',
        'color_hex',
        'metadata',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttributeValueStatus::class,
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * @return HasMany<AttributeValueTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeValueTranslation::class);
    }

    /**
     * Variant assignment rows referencing this value.
     *
     * @return HasMany<ProductVariantAttributeValue, $this>
     */
    public function variantAssignments(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeValue::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function translation(?string $locale = null): ?AttributeValueTranslation
    {
        $locale ??= CatalogLocales::default();
        $exact = $this->translations->firstWhere('locale', $locale);
        if ($exact !== null) {
            return $exact;
        }

        return $this->translations->firstWhere('locale', CatalogLocales::fallback());
    }

    public function localizedName(?string $locale = null): ?string
    {
        return $this->translation($locale)?->name;
    }

    protected static function newFactory(): AttributeValueFactory
    {
        return AttributeValueFactory::new();
    }
}
