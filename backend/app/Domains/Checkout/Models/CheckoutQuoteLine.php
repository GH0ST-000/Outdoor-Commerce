<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $checkout_quote_id
 * @property string $public_id
 * @property int $product_id
 * @property int $variant_id
 * @property string $sku
 * @property string $product_name
 * @property string $variant_label
 * @property string|null $slug
 * @property int $quantity
 * @property int $unit_base_price_minor
 * @property int $unit_effective_price_minor
 * @property int $unit_discount_minor
 * @property int $line_subtotal_minor
 * @property int $line_discount_minor
 * @property int $line_total_minor
 * @property string $currency
 * @property array<string, mixed>|null $promotion_snapshot
 * @property array<string, mixed>|null $attribute_snapshot
 * @property array<string, mixed>|null $media_snapshot
 * @property array<string, mixed>|null $restriction_snapshot
 * @property string|null $reservation_key
 */
class CheckoutQuoteLine extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'checkout_quote_id',
        'public_id',
        'product_id',
        'variant_id',
        'sku',
        'product_name',
        'variant_label',
        'slug',
        'quantity',
        'unit_base_price_minor',
        'unit_effective_price_minor',
        'unit_discount_minor',
        'line_subtotal_minor',
        'line_discount_minor',
        'line_total_minor',
        'currency',
        'promotion_snapshot',
        'attribute_snapshot',
        'media_snapshot',
        'restriction_snapshot',
        'reservation_key',
        'created_at',
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
            'promotion_snapshot' => 'array',
            'attribute_snapshot' => 'array',
            'media_snapshot' => 'array',
            'restriction_snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<CheckoutQuote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class, 'checkout_quote_id');
    }

    /**
     * @return HasMany<CheckoutQuoteAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(CheckoutQuoteAdjustment::class, 'quote_line_id');
    }
}
