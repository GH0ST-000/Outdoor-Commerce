<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Services;

use App\Domains\Checkout\DTOs\CheckoutActorData;
use App\Domains\Checkout\Exceptions\CheckoutIdempotencyConflictException;
use App\Domains\Checkout\Exceptions\CheckoutIdempotencyReplayException;
use App\Domains\Checkout\Models\CheckoutIdempotencyRecord;
use App\Domains\Checkout\Support\CheckoutLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Database\QueryException;

final class CheckoutIdempotencyService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly CheckoutLogger $logger,
    ) {}

    public function scope(CheckoutActorData $actor): string
    {
        if ($actor->userId() !== null) {
            return 'user:'.$actor->userId();
        }

        $hash = $actor->cartActor->rawGuestToken;
        if (is_string($hash) && $hash !== '') {
            return 'guest:'.hash_hmac('sha256', $hash, (string) config('app.key'));
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
    public function begin(CheckoutActorData $actor, array $payload): ?CheckoutIdempotencyRecord
    {
        if ($actor->idempotencyKey === null || $actor->endpoint === null) {
            return null;
        }

        $scope = $this->scope($actor);
        $hash = $this->payloadHash($payload);
        $existing = CheckoutIdempotencyRecord::query()
            ->where('scope', $scope)
            ->where('idempotency_key', $actor->idempotencyKey)
            ->first();

        if ($existing !== null) {
            if ($existing->payload_hash !== $hash) {
                throw new CheckoutIdempotencyConflictException;
            }

            if ($existing->status_code !== null && is_array($existing->response_body)) {
                $this->logger->info('idempotency_replay', [
                    'endpoint' => $actor->endpoint,
                    'scope' => $scope,
                ]);
                throw new CheckoutIdempotencyReplayException($existing->status_code, $existing->response_body);
            }

            return $existing;
        }

        try {
            return CheckoutIdempotencyRecord::query()->create([
                'scope' => $scope,
                'idempotency_key' => $actor->idempotencyKey,
                'payload_hash' => $hash,
                'endpoint' => $actor->endpoint,
                'expires_at' => $this->clock->now()->addHours((int) config('checkout.idempotency_ttl_hours', 24)),
            ]);
        } catch (QueryException) {
            $existing = CheckoutIdempotencyRecord::query()
                ->where('scope', $scope)
                ->where('idempotency_key', $actor->idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $hash) {
                    throw new CheckoutIdempotencyConflictException;
                }
                if ($existing->status_code !== null && is_array($existing->response_body)) {
                    throw new CheckoutIdempotencyReplayException($existing->status_code, $existing->response_body);
                }

                return $existing;
            }

            throw new CheckoutIdempotencyConflictException;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function complete(CheckoutIdempotencyRecord $record, array $body, int $statusCode = 200): void
    {
        $record->status_code = $statusCode;
        $record->response_body = $body;
        $record->save();
    }
}
