<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Services;

use App\Domains\Shared\Support\Clock;
use App\Domains\Shipping\Exceptions\ShipmentIdempotencyConflictException;
use App\Domains\Shipping\Exceptions\ShipmentIdempotencyReplayException;
use App\Domains\Shipping\Models\ShipmentIdempotencyRecord;
use Illuminate\Database\QueryException;

final class ShipmentIdempotencyService
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function begin(int $actorUserId, string $endpoint, string $key, array $payload): ShipmentIdempotencyRecord
    {
        $scope = 'user:'.$actorUserId;
        $hash = $this->hash($payload);
        $existing = ShipmentIdempotencyRecord::query()
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing !== null) {
            return $this->existing($existing, $hash);
        }

        try {
            return ShipmentIdempotencyRecord::query()->create([
                'scope' => $scope,
                'idempotency_key' => $key,
                'payload_hash' => $hash,
                'endpoint' => $endpoint,
                'expires_at' => $this->clock->now()->addHours((int) config('shipping.idempotency_ttl_hours', 24)),
            ]);
        } catch (QueryException) {
            $existing = ShipmentIdempotencyRecord::query()
                ->where('scope', $scope)
                ->where('idempotency_key', $key)
                ->first();
            if ($existing === null) {
                throw new ShipmentIdempotencyConflictException;
            }

            return $this->existing($existing, $hash);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function complete(ShipmentIdempotencyRecord $record, array $body, int $statusCode): void
    {
        $record->status_code = $statusCode;
        $record->response_body = $body;
        $record->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function existing(ShipmentIdempotencyRecord $existing, string $hash): ShipmentIdempotencyRecord
    {
        if ($existing->payload_hash !== $hash) {
            throw new ShipmentIdempotencyConflictException;
        }
        if ($existing->status_code !== null && is_array($existing->response_body)) {
            throw new ShipmentIdempotencyReplayException($existing->status_code, $existing->response_body);
        }

        return $existing;
    }
}
