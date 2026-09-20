<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Enums\InventoryReservationStatus;
use App\Models\User;
use Database\Factories\InventoryReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reservation_key
 * @property int $warehouse_id
 * @property int $product_variant_id
 * @property int $quantity
 * @property InventoryReservationStatus $status
 * @property string $idempotency_key
 * @property string|null $payload_hash
 * @property Carbon|null $expires_at
 * @property Carbon|null $committed_at
 * @property Carbon|null $released_at
 * @property string|null $release_reason
 */
class InventoryReservation extends Model
{
    /** @use HasFactory<InventoryReservationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reservation_key',
        'warehouse_id',
        'product_variant_id',
        'quantity',
        'status',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'payload_hash',
        'expires_at',
        'committed_at',
        'released_at',
        'release_reason',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InventoryReservationStatus::class,
            'quantity' => 'integer',
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): InventoryReservationFactory
    {
        return InventoryReservationFactory::new();
    }
}
