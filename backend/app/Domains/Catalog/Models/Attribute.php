<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\AttributeStatus;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Models\User;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property AttributeType $type
 * @property AttributeStatus $status
 * @property bool $is_filterable
 * @property int $sort_order
 */
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'type',
        'status',
        'is_filterable',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'status' => AttributeStatus::class,
            'is_filterable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<AttributeTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeTranslation::class);
    }

    /**
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }

    /**
     * Products using this attribute as a variant axis.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attributes')
            ->withPivot('sort_order')
            ->withTimestamps();
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

    public function translation(?string $locale = null): ?AttributeTranslation
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

    protected static function newFactory(): AttributeFactory
    {
        return AttributeFactory::new();
    }
}
