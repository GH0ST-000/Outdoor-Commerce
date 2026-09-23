<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Models\CheckoutQuote;
use App\Domains\Checkout\Models\CheckoutSession;
use App\Domains\Identity\Models\User;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Durable commercial order created by consuming an active checkout quote.
 *
 * @property int $id
 * @property string $public_id
 * @property string $order_number
 * @property int|null $user_id
 * @property int $checkout_session_id
 * @property int $checkout_quote_id
 * @property int $cart_id
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property FulfillmentStatus $fulfillment_status
 * @property string $currency
 * @property int $items_subtotal_minor
 * @property int $discount_total_minor
 * @property int $delivery_total_minor
 * @property int $tax_total_minor
 * @property int $grand_total_minor
 * @property bool $price_includes_tax
 * @property string $customer_email
 * @property string $customer_phone
 * @property string $customer_first_name
 * @property string $customer_last_name
 * @property string|null $customer_note
 * @property string $fulfillment_method_code
 * @property string $fulfillment_method_name
 * @property int $quote_revision
 * @property string $quote_fingerprint
 * @property array<string, mixed> $contact_snapshot
 * @property array<string, mixed>|null $shipping_address_snapshot
 * @property array<string, mixed>|null $billing_address_snapshot
 * @property array<string, mixed> $fulfillment_snapshot
 * @property string|null $access_token_hash
 * @property CarbonImmutable|null $reservation_expires_at
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $paid_at
 * @property int $version
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'order_number',
        'user_id',
        'checkout_session_id',
        'checkout_quote_id',
        'cart_id',
        'status',
        'payment_status',
        'fulfillment_status',
        'currency',
        'items_subtotal_minor',
        'discount_total_minor',
        'delivery_total_minor',
        'tax_total_minor',
        'grand_total_minor',
        'price_includes_tax',
        'customer_email',
        'customer_phone',
        'customer_first_name',
        'customer_last_name',
        'customer_note',
        'fulfillment_method_code',
        'fulfillment_method_name',
        'quote_revision',
        'quote_fingerprint',
        'contact_snapshot',
        'shipping_address_snapshot',
        'billing_address_snapshot',
        'fulfillment_snapshot',
        'access_token_hash',
        'reservation_expires_at',
        'placed_at',
        'confirmed_at',
        'cancelled_at',
        'expired_at',
        'paid_at',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'items_subtotal_minor' => 'integer',
            'discount_total_minor' => 'integer',
            'delivery_total_minor' => 'integer',
            'tax_total_minor' => 'integer',
            'grand_total_minor' => 'integer',
            'price_includes_tax' => 'boolean',
            'quote_revision' => 'integer',
            'contact_snapshot' => 'encrypted:array',
            'shipping_address_snapshot' => 'encrypted:array',
            'billing_address_snapshot' => 'encrypted:array',
            'fulfillment_snapshot' => 'array',
            'reservation_expires_at' => 'immutable_datetime',
            'placed_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CheckoutSession, $this>
     */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    /**
     * @return BelongsTo<CheckoutQuote, $this>
     */
    public function checkoutQuote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class);
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
