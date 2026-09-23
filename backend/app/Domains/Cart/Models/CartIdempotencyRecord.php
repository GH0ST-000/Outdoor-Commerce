<?php

declare(strict_types=1);

namespace App\Domains\Cart\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $scope
 * @property string $idempotency_key
 * @property string $payload_hash
 * @property string $endpoint
 * @property int|null $cart_id
 * @property int|null $status_code
 * @property array<string, mixed>|null $response_body
 * @property CarbonImmutable $expires_at
 */
class CartIdempotencyRecord extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'scope',
        'idempotency_key',
        'payload_hash',
        'endpoint',
        'cart_id',
        'status_code',
        'response_body',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'status_code' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
