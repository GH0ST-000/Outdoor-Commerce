<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Enums\CheckoutSessionStatus;
use App\Domains\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CheckoutSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mutable checkout workflow container. Not an order.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $user_id
 * @property int $cart_id
 * @property string|null $guest_token_hash
 * @property CheckoutSessionStatus $status
 * @property int $version
 * @property string $currency
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $customer_note
 * @property int|null $fulfillment_method_id
 * @property int|null $pickup_location_id
 * @property int|null $shipping_address_id
 * @property bool $billing_same_as_shipping
 * @property int|null $billing_address_id
 * @property int|null $current_quote_id
 * @property int $quote_refresh_count
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $last_activity_at
 * @property int|null $converted_order_id
 */
class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'user_id',
        'cart_id',
        'guest_token_hash',
        'status',
        'version',
        'currency',
        'email',
        'phone',
        'first_name',
        'last_name',
        'customer_note',
        'fulfillment_method_id',
        'pickup_location_id',
        'shipping_address_id',
        'billing_same_as_shipping',
        'billing_address_id',
        'current_quote_id',
        'quote_refresh_count',
        'expires_at',
        'last_activity_at',
        'converted_order_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CheckoutSessionStatus::class,
            'version' => 'integer',
            'billing_same_as_shipping' => 'boolean',
            'quote_refresh_count' => 'integer',
            'expires_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FulfillmentMethod, $this>
     */
    public function fulfillmentMethod(): BelongsTo
    {
        return $this->belongsTo(FulfillmentMethod::class);
    }

    /**
     * @return BelongsTo<PickupLocation, $this>
     */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(PickupLocation::class);
    }

    /**
     * @return BelongsTo<CheckoutAddress, $this>
     */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CheckoutAddress::class, 'shipping_address_id');
    }

    /**
     * @return BelongsTo<CheckoutAddress, $this>
     */
    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(CheckoutAddress::class, 'billing_address_id');
    }

    /**
     * @return BelongsTo<CheckoutQuote, $this>
     */
    public function currentQuote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class, 'current_quote_id');
    }

    /**
     * @return HasMany<CheckoutQuote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(CheckoutQuote::class);
    }

    public function hasContact(): bool
    {
        return is_string($this->email) && $this->email !== ''
            && is_string($this->phone) && $this->phone !== ''
            && is_string($this->first_name) && $this->first_name !== ''
            && is_string($this->last_name) && $this->last_name !== '';
    }

    protected static function newFactory(): CheckoutSessionFactory
    {
        return CheckoutSessionFactory::new();
    }
}
