<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rebuildable public variant read model. Not a source of pricing or inventory truth.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int $product_id
 * @property string $currency_code
 * @property int|null $base_price_minor
 * @property int|null $final_price_minor
 * @property int|null $discount_amount_minor
 * @property bool $on_sale
 * @property int $available_to_sell
 * @property bool $is_in_stock
 * @property bool $is_low_stock
 * @property bool $is_public
 * @property string|null $pricing_signature
 * @property int $pricing_version
 * @property int $inventory_version
 * @property Carbon|null $projected_at
 */
class PublicCatalogVariantProjection extends Model
{
    protected $table = 'public_catalog_variant_projections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_variant_id',
        'product_id',
        'currency_code',
        'base_price_minor',
        'final_price_minor',
        'discount_amount_minor',
        'on_sale',
        'available_to_sell',
        'is_in_stock',
        'is_low_stock',
        'is_public',
        'pricing_signature',
        'pricing_version',
        'inventory_version',
        'projected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_sale' => 'boolean',
            'is_in_stock' => 'boolean',
            'is_low_stock' => 'boolean',
            'is_public' => 'boolean',
            'base_price_minor' => 'integer',
            'final_price_minor' => 'integer',
            'discount_amount_minor' => 'integer',
            'available_to_sell' => 'integer',
            'pricing_version' => 'integer',
            'inventory_version' => 'integer',
            'projected_at' => 'datetime',
        ];
    }
}
