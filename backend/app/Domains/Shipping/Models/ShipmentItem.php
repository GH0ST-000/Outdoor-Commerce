<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Models;

use App\Domains\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantity of one order item assigned to a shipment.
 *
 * @property int $id
 * @property int $shipment_id
 * @property int $order_item_id
 * @property int $quantity
 * @property int $picked_quantity
 * @property int $packed_quantity
 * @property int $shipped_quantity
 * @property int $delivered_quantity
 * @property int $cancelled_quantity
 */
class ShipmentItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shipment_id',
        'order_item_id',
        'quantity',
        'picked_quantity',
        'packed_quantity',
        'shipped_quantity',
        'delivered_quantity',
        'cancelled_quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'picked_quantity' => 'integer',
            'packed_quantity' => 'integer',
            'shipped_quantity' => 'integer',
            'delivered_quantity' => 'integer',
            'cancelled_quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
