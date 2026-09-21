<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\ValueObjects\InventoryQuantities;
use Database\Factories\InventoryBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_variant_id
 * @property int $on_hand
 * @property int $reserved
 * @property int $safety_stock
 * @property int $reorder_point
 * @property int $version
 * @property Carbon|null $last_movement_at
 */
class InventoryBalance extends Model
{
    /** @use HasFactory<InventoryBalanceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'on_hand',
        'reserved',
        'safety_stock',
        'reorder_point',
        'version',
        'last_movement_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'safety_stock' => 'integer',
            'reorder_point' => 'integer',
            'version' => 'integer',
            'last_movement_at' => 'datetime',
        ];
    }

    public function quantities(): InventoryQuantities
    {
        return new InventoryQuantities(
            $this->on_hand,
            $this->reserved,
            $this->safety_stock,
            $this->reorder_point,
        );
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

    protected static function newFactory(): InventoryBalanceFactory
    {
        return InventoryBalanceFactory::new();
    }
}
