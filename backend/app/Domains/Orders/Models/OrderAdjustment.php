<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Domains\Orders\Enums\OrderAdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $order_item_id
 * @property OrderAdjustmentType $type
 * @property string $code
 * @property string $label
 * @property int $amount_minor
 * @property array<string, mixed>|null $metadata
 */
class OrderAdjustment extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'order_item_id',
        'type',
        'code',
        'label',
        'amount_minor',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderAdjustmentType::class,
            'amount_minor' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
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
     * @return BelongsTo<OrderItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
