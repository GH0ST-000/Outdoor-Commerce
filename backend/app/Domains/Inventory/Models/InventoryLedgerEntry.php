<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Models;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Enums\InventoryMovementType;
use Database\Factories\InventoryLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inventory_operation_id
 * @property int $warehouse_id
 * @property int $product_variant_id
 * @property InventoryMovementType $movement_type
 * @property int $quantity_delta
 * @property int $on_hand_after
 * @property int $reserved_after
 * @property Carbon|null $created_at
 */
class InventoryLedgerEntry extends Model
{
    /** @use HasFactory<InventoryLedgerEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inventory_operation_id',
        'warehouse_id',
        'product_variant_id',
        'movement_type',
        'quantity_delta',
        'on_hand_after',
        'reserved_after',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => InventoryMovementType::class,
            'quantity_delta' => 'integer',
            'on_hand_after' => 'integer',
            'reserved_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<InventoryOperation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(InventoryOperation::class, 'inventory_operation_id');
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

    protected static function newFactory(): InventoryLedgerEntryFactory
    {
        return InventoryLedgerEntryFactory::new();
    }
}
