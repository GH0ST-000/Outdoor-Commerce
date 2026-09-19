<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\CatalogStatus;
use App\Domains\Catalog\Support\CatalogLocales;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property CatalogStatus $status
 * @property int $sort_order
 * @property bool $is_featured
 */
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'status',
        'sort_order',
        'is_featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CatalogStatus::class,
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return HasMany<BrandTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(BrandTranslation::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function translation(?string $locale = null): ?BrandTranslation
    {
        $locale ??= CatalogLocales::default();
        $fallback = CatalogLocales::fallback();

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', $fallback);
    }

    public function localizedName(?string $locale = null): ?string
    {
        return $this->translation($locale)?->name;
    }

    protected static function newFactory(): BrandFactory
    {
        return BrandFactory::new();
    }
}
