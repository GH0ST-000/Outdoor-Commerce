<?php

declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Exceptions\OrderIdempotencyConflictException;
use App\Domains\Orders\Exceptions\OrderIdempotencyReplayException;
use App\Domains\Orders\Models\OrderIdempotencyRecord;
use App\Domains\Orders\Support\OrderLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Database\QueryException;

final class OrderIdempotencyService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly OrderLogger $logger,
    ) {}

    public function scope(OrderActorData $actor): string
    {
        if ($actor->userId() !== null) {
            return 'user:'.$actor->userId();
        }

        $guest = $actor->checkout->cartActor->rawGuestToken;
        if (is_string($guest) && $guest !== '') {
            return 'guest:'.hash_hmac('sha256', $guest, (string) config('app.key'));
        }

        $orderToken = $actor->rawOrderToken;
        if (is_string($orderToken) && $orderToken !== '') {
            return 'order:'.hash_hmac('sha256', $orderToken, (string) config('app.key'));
        }

        return 'anon';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function begin(OrderActorData $actor, array $payload): ?OrderIdempotencyRecord
    {
        if ($actor->idempotencyKey === null || $actor->endpoint === null) {
            return null;
        }

        $scope = $this->scope($actor);
        $hash = $this->payloadHash($payload);
        $existing = OrderIdempotencyRecord::query()
            ->where('scope', $scope)
            ->where('idempotency_key', $actor->idempotencyKey)
            ->first();

        if ($existing !== null) {
            if ($existing->payload_hash !== $hash) {
                throw new OrderIdempotencyConflictException;
            }

            if ($existing->status_code !== null && is_array($existing->response_body)) {
                $this->logger->info('idempotency_replay', [
                    'endpoint' => $actor->endpoint,
                    'scope' => $scope,
                ]);
                throw new OrderIdempotencyReplayException($existing->status_code, $existing->response_body);
            }

            return $existing;
        }

        try {
            return OrderIdempotencyRecord::query()->create([
                'scope' => $scope,
                'idempotency_key' => $actor->idempotencyKey,
                'payload_hash' => $hash,
                'endpoint' => $actor->endpoint,
                'expires_at' => $this->clock->now()->addHours((int) config('order.idempotency_ttl_hours', 24)),
            ]);
        } catch (QueryException) {
            $existing = OrderIdempotencyRecord::query()
                ->where('scope', $scope)
                ->where('idempotency_key', $actor->idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $hash) {
                    throw new OrderIdempotencyConflictException;
                }
                if ($existing->status_code !== null && is_array($existing->response_body)) {
                    throw new OrderIdempotencyReplayException($existing->status_code, $existing->response_body);
                }

                return $existing;
            }

            throw new OrderIdempotencyConflictException;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function complete(OrderIdempotencyRecord $record, array $body, int $statusCode): void
    {
        $record->status_code = $statusCode;
        $record->response_body = $body;
        $record->save();
    }
}
