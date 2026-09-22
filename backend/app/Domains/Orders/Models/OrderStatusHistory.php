<?php

declare(strict_types=1);

namespace App\Domains\Orders\Models;

use App\Domains\Orders\Enums\OrderActorType;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\OrderStatusReasonCode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only order status transition.
 *
 * @property int $id
 * @property int $order_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property OrderStatusReasonCode $reason_code
 * @property OrderActorType $actor_type
 * @property int|null $actor_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 */
class OrderStatusHistory extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'reason_code',
        'actor_type',
        'actor_id',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'reason_code' => OrderStatusReasonCode::class,
            'actor_type' => OrderActorType::class,
            'actor_id' => 'integer',
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
}
