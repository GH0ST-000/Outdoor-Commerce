<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $scope
 * @property string $idempotency_key
 * @property string $payload_hash
 * @property string $endpoint
 * @property int|null $status_code
 * @property array<string, mixed>|null $response_body
 */
class ShipmentIdempotencyRecord extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'scope',
        'idempotency_key',
        'payload_hash',
        'endpoint',
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
            'status_code' => 'integer',
            'response_body' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
