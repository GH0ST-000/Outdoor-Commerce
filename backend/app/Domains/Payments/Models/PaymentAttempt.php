<?php

declare(strict_types=1);

namespace App\Domains\Payments\Models;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\PaymentActionType;
use App\Domains\Payments\Enums\PaymentAttemptStatus;
use App\Domains\Payments\Enums\PaymentFailureCategory;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable history of a hosted-redirect payment attempt for one order.
 *
 * Amount and currency are copied from the order at creation and never taken
 * from the browser. Provider SDK payloads are not stored.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property string $provider
 * @property string $payment_method_code
 * @property PaymentAttemptStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $provider_payment_id
 * @property string|null $provider_transaction_id
 * @property string|null $provider_status
 * @property string $idempotency_key_hash
 * @property string $request_fingerprint
 * @property PaymentActionType|null $action_type
 * @property string|null $redirect_url_encrypted
 * @property CarbonImmutable|null $provider_expires_at
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property PaymentFailureCategory|null $failure_category
 * @property CarbonImmutable|null $authorized_at
 * @property CarbonImmutable|null $captured_at
 * @property CarbonImmutable|null $succeeded_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $last_provider_sync_at
 * @property int $version
 */
class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'order_id',
        'provider',
        'payment_method_code',
        'status',
        'amount_minor',
        'currency',
        'provider_payment_id',
        'provider_transaction_id',
        'provider_status',
        'idempotency_key_hash',
        'request_fingerprint',
        'action_type',
        'redirect_url_encrypted',
        'provider_expires_at',
        'failure_code',
        'failure_message',
        'failure_category',
        'authorized_at',
        'captured_at',
        'succeeded_at',
        'failed_at',
        'cancelled_at',
        'expired_at',
        'last_provider_sync_at',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'amount_minor' => 'integer',
            'action_type' => PaymentActionType::class,
            'redirect_url_encrypted' => 'encrypted',
            'provider_expires_at' => 'immutable_datetime',
            'failure_category' => PaymentFailureCategory::class,
            'authorized_at' => 'immutable_datetime',
            'captured_at' => 'immutable_datetime',
            'succeeded_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'last_provider_sync_at' => 'immutable_datetime',
            'version' => 'integer',
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
     * @return HasMany<PaymentAttemptStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(PaymentAttemptStatusHistory::class);
    }

    protected static function newFactory(): PaymentAttemptFactory
    {
        return PaymentAttemptFactory::new();
    }
}
