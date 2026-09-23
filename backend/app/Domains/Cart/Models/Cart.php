<?php

declare(strict_types=1);

namespace App\Domains\Cart\Models;

use App\Domains\Cart\Enums\CartStatus;
use App\Domains\Identity\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Server-authoritative shopper cart. MySQL is the source of truth.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $user_id
 * @property string|null $guest_token_hash
 * @property CartStatus $status
 * @property string $currency
 * @property int $version
 * @property int $item_count
 * @property int $unique_item_count
 * @property int $subtotal_minor
 * @property int $discount_total_minor
 * @property int $total_minor
 * @property CarbonImmutable|null $last_activity_at
 * @property CarbonImmutable|null $expires_at
 * @property int|null $merged_into_cart_id
 * @property int|null $converted_order_id
 */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'user_id',
        'guest_token_hash',
        'status',
        'currency',
        'version',
        'item_count',
        'unique_item_count',
        'subtotal_minor',
        'discount_total_minor',
        'total_minor',
        'last_activity_at',
        'expires_at',
        'merged_into_cart_id',
        'converted_order_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
            'version' => 'integer',
            'item_count' => 'integer',
            'unique_item_count' => 'integer',
            'subtotal_minor' => 'integer',
            'discount_total_minor' => 'integer',
            'total_minor' => 'integer',
            'last_activity_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_cart_id');
    }

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }
}
