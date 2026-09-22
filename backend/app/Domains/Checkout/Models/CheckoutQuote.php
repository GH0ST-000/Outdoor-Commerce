<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use App\Domains\Checkout\Enums\CheckoutQuoteStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CheckoutQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable quote revision. Recalculation creates a new row.
 *
 * @property int $id
 * @property string $public_id
 * @property int $checkout_session_id
 * @property int $revision
 * @property CheckoutQuoteStatus $status
 * @property int $cart_version
 * @property string $currency
 * @property int $items_subtotal_minor
 * @property int $discount_total_minor
 * @property int $delivery_total_minor
 * @property int $tax_total_minor
 * @property int $grand_total_minor
 * @property bool $price_includes_tax
 * @property string $quote_fingerprint
 * @property array<string, mixed>|null $fulfillment_snapshot
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $superseded_at
 * @property CarbonImmutable|null $consumed_at
 */
class CheckoutQuote extends Model
{
    /** @use HasFactory<CheckoutQuoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'checkout_session_id',
        'revision',
        'status',
        'cart_version',
        'currency',
        'items_subtotal_minor',
        'discount_total_minor',
        'delivery_total_minor',
        'tax_total_minor',
        'grand_total_minor',
        'price_includes_tax',
        'quote_fingerprint',
        'fulfillment_snapshot',
        'expires_at',
        'superseded_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CheckoutQuoteStatus::class,
            'revision' => 'integer',
            'cart_version' => 'integer',
            'items_subtotal_minor' => 'integer',
            'discount_total_minor' => 'integer',
            'delivery_total_minor' => 'integer',
            'tax_total_minor' => 'integer',
            'grand_total_minor' => 'integer',
            'price_includes_tax' => 'boolean',
            'fulfillment_snapshot' => 'array',
            'expires_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<CheckoutSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }

    /**
     * @return HasMany<CheckoutQuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CheckoutQuoteLine::class);
    }

    /**
     * @return HasMany<CheckoutQuoteAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(CheckoutQuoteAdjustment::class);
    }

    protected static function newFactory(): CheckoutQuoteFactory
    {
        return CheckoutQuoteFactory::new();
    }
}
