<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable commercial snapshot of a purchased line.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string $sku
 * @property string $product_name
 * @property string $variant_name
 * @property int $quantity
 * @property int $unit_base_price_minor
 * @property int $unit_effective_price_minor
 * @property int $unit_discount_minor
 * @property int $line_subtotal_minor
 * @property int $line_discount_minor
 * @property int $line_total_minor
 * @property string $currency
 * @property array<string, mixed> $product_snapshot
 * @property array<string, mixed> $variant_snapshot
 * @property array<string, mixed>|null $attribute_snapshot
 * @property array<string, mixed>|null $promotion_snapshot
 * @property array<string, mixed>|null $media_snapshot
 * @property array<string, mixed>|null $restriction_snapshot
 * @property string|null $reservation_key
 */
class OrderItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'order_id',
        'product_id',
        'variant_id',
        'sku',
        'product_name',
        'variant_name',
        'quantity',
        'unit_base_price_minor',
        'unit_effective_price_minor',
        'unit_discount_minor',
        'line_subtotal_minor',
        'line_discount_minor',
        'line_total_minor',
        'currency',
        'product_snapshot',
        'variant_snapshot',
        'attribute_snapshot',
        'promotion_snapshot',
        'media_snapshot',
        'restriction_snapshot',
        'reservation_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'quantity' => 'integer',
            'unit_base_price_minor' => 'integer',
            'unit_effective_price_minor' => 'integer',
            'unit_discount_minor' => 'integer',
            'line_subtotal_minor' => 'integer',
            'line_discount_minor' => 'integer',
            'line_total_minor' => 'integer',
            'product_snapshot' => 'array',
            'variant_snapshot' => 'array',
            'attribute_snapshot' => 'array',
            'promotion_snapshot' => 'array',
            'media_snapshot' => 'array',
            'restriction_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    /**
     * @return HasMany<OrderAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }
}
