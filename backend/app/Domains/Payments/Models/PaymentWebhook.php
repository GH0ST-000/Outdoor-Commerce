<?php

declare(strict_types=1);

namespace App\Domains\Payments\Models;

use App\Domains\Payments\Enums\PaymentWebhookProcessingStatus;
use App\Domains\Payments\Enums\PaymentWebhookSignatureStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Durable webhook inbox. Raw provider bytes are used for signature
 * verification before this row is written; only sanitized fields persist.
 *
 * @property int $id
 * @property string $public_id
 * @property string $provider
 * @property string|null $provider_event_id
 * @property string $payload_hash
 * @property string|null $normalized_event_type
 * @property string|null $provider_payment_id
 * @property PaymentWebhookSignatureStatus $signature_status
 * @property PaymentWebhookProcessingStatus $processing_status
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $failed_at
 * @property int $attempt_count
 * @property string|null $last_error_code
 * @property array<string, mixed>|null $safe_payload
 * @property array<string, mixed>|null $safe_headers
 */
class PaymentWebhook extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'provider',
        'provider_event_id',
        'payload_hash',
        'normalized_event_type',
        'provider_payment_id',
        'signature_status',
        'processing_status',
        'received_at',
        'processed_at',
        'failed_at',
        'attempt_count',
        'last_error_code',
        'safe_payload',
        'safe_headers',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_status' => PaymentWebhookSignatureStatus::class,
            'processing_status' => PaymentWebhookProcessingStatus::class,
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'attempt_count' => 'integer',
            'safe_payload' => 'array',
            'safe_headers' => 'array',
        ];
    }
}
