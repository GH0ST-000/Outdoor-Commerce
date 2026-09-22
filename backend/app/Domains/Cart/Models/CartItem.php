<?php

declare(strict_types=1);

namespace App\Domains\Cart\Models;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $cart_id
 * @property int $product_id
 * @property int $variant_id
 * @property int $quantity
 * @property int $unit_price_at_add_minor
 * @property int $discount_at_add_minor
 * @property string $currency
 * @property string|null $configuration_hash
 * @property string|null $product_name_at_add
 * @property string|null $variant_name_at_add
 * @property string|null $sku_at_add
 * @property CarbonImmutable $added_at
 */
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price_at_add_minor',
        'discount_at_add_minor',
        'currency',
        'configuration_hash',
        'product_name_at_add',
        'variant_name_at_add',
        'sku_at_add',
        'added_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_at_add_minor' => 'integer',
            'discount_at_add_minor' => 'integer',
            'added_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    protected static function newFactory(): CartItemFactory
    {
        return CartItemFactory::new();
    }
}
