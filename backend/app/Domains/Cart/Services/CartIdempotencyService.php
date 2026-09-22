<?php

declare(strict_types=1);

namespace App\Domains\Cart\Services;

use App\Domains\Cart\DTOs\CartActorData;
use App\Domains\Cart\Exceptions\CartIdempotencyConflictException;
use App\Domains\Cart\Exceptions\CartIdempotencyReplayException;
use App\Domains\Cart\Models\CartIdempotencyRecord;
use App\Domains\Cart\Support\CartLogger;
use App\Domains\Cart\Support\CartTokenHasher;
use App\Domains\Shared\Support\Clock;
use Illuminate\Database\QueryException;

final class CartIdempotencyService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly CartTokenHasher $tokens,
        private readonly CartLogger $logger,
    ) {}

    public function scope(CartActorData $actor): string
    {
        if ($actor->userId !== null) {
            return 'user:'.$actor->userId;
        }

        if (is_string($actor->rawGuestToken) && $actor->rawGuestToken !== '') {
            return 'guest:'.$this->tokens->hash($actor->rawGuestToken);
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
    public function begin(CartActorData $actor, array $payload): ?CartIdempotencyRecord
    {
        if ($actor->idempotencyKey === null || $actor->endpoint === null) {
            return null;
        }

        $scope = $this->scope($actor);
        $hash = $this->payloadHash($payload);
        $existing = CartIdempotencyRecord::query()
            ->where('scope', $scope)
            ->where('idempotency_key', $actor->idempotencyKey)
            ->first();

        if ($existing !== null) {
            if ($existing->payload_hash !== $hash) {
                throw new CartIdempotencyConflictException;
            }

            if ($existing->status_code !== null && is_array($existing->response_body)) {
                $this->logger->info('idempotency_replay', [
                    'endpoint' => $actor->endpoint,
                    'scope' => $scope,
                ]);
                throw new CartIdempotencyReplayException($existing->status_code, $existing->response_body);
            }

            return $existing;
        }

        try {
            return CartIdempotencyRecord::query()->create([
                'scope' => $scope,
                'idempotency_key' => $actor->idempotencyKey,
                'payload_hash' => $hash,
                'endpoint' => $actor->endpoint,
                'expires_at' => $this->clock->now()->addHours((int) config('cart.idempotency_ttl_hours', 24)),
            ]);
        } catch (QueryException) {
            $existing = CartIdempotencyRecord::query()
                ->where('scope', $scope)
                ->where('idempotency_key', $actor->idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $hash) {
                    throw new CartIdempotencyConflictException;
                }
                if ($existing->status_code !== null && is_array($existing->response_body)) {
                    throw new CartIdempotencyReplayException($existing->status_code, $existing->response_body);
                }

                return $existing;
            }

            throw new CartIdempotencyConflictException;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function complete(CartIdempotencyRecord $record, array $body, int $statusCode = 200): void
    {
        $record->status_code = $statusCode;
        $record->response_body = $body;
        $record->save();
    }
}
