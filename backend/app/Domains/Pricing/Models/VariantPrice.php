<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Models;

use App\Domains\Catalog\Models\ProductVariant;
use Database\Factories\VariantPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aggregate root for one variant in one price list.
 *
 * @property int $id
 * @property int $price_list_id
 * @property int $product_variant_id
 * @property int $version
 */
class VariantPrice extends Model
{
    /** @use HasFactory<VariantPriceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'price_list_id',
        'product_variant_id',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return HasMany<PricePeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(PricePeriod::class);
    }

    protected static function newFactory(): VariantPriceFactory
    {
        return VariantPriceFactory::new();
    }
}
