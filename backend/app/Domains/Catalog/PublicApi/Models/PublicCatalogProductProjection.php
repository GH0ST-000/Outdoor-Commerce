<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rebuildable public product read model. Not a source of pricing or inventory truth.
 *
 * @property int $id
 * @property int $product_id
 * @property string $currency_code
 * @property int|null $minimum_base_price_minor
 * @property int|null $maximum_base_price_minor
 * @property int|null $minimum_final_price_minor
 * @property int|null $maximum_final_price_minor
 * @property int $public_variant_count
 * @property int $in_stock_variant_count
 * @property bool $is_in_stock
 * @property bool $is_on_sale
 * @property bool $is_public
 * @property int|null $default_variant_id
 * @property int $pricing_version
 * @property int $inventory_version
 * @property Carbon|null $projected_at
 */
class PublicCatalogProductProjection extends Model
{
    protected $table = 'public_catalog_product_projections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'currency_code',
        'minimum_base_price_minor',
        'maximum_base_price_minor',
        'minimum_final_price_minor',
        'maximum_final_price_minor',
        'public_variant_count',
        'in_stock_variant_count',
        'is_in_stock',
        'is_on_sale',
        'is_public',
        'default_variant_id',
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
            'is_in_stock' => 'boolean',
            'is_on_sale' => 'boolean',
            'is_public' => 'boolean',
            'minimum_base_price_minor' => 'integer',
            'maximum_base_price_minor' => 'integer',
            'minimum_final_price_minor' => 'integer',
            'maximum_final_price_minor' => 'integer',
            'public_variant_count' => 'integer',
            'in_stock_variant_count' => 'integer',
            'default_variant_id' => 'integer',
            'pricing_version' => 'integer',
            'inventory_version' => 'integer',
            'projected_at' => 'datetime',
        ];
    }
}
