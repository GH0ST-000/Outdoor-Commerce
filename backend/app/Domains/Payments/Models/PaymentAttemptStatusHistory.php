<?php

declare(strict_types=1);

namespace App\Domains\Payments\Models;

use App\Domains\Payments\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payment_attempt_id
 * @property PaymentAttemptStatus|null $from_status
 * @property PaymentAttemptStatus $to_status
 * @property string|null $reason_code
 * @property array<string, mixed>|null $metadata
 */
class PaymentAttemptStatusHistory extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_attempt_id',
        'from_status',
        'to_status',
        'reason_code',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => PaymentAttemptStatus::class,
            'to_status' => PaymentAttemptStatus::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<PaymentAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }
}
