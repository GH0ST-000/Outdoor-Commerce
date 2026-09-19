<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Models;

use Database\Factories\ProductTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $locale
 * @property string $name
 * @property string $slug
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $seo_title
 * @property string|null $seo_description
 */
class ProductTranslation extends Model
{
    /** @use HasFactory<ProductTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'locale',
        'name',
        'slug',
        'short_description',
        'description',
        'seo_title',
        'seo_description',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory(): ProductTranslationFactory
    {
        return ProductTranslationFactory::new();
    }
}
