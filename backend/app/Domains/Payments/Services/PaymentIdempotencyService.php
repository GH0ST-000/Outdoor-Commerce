<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Payments\Exceptions\PaymentIdempotencyConflictException;
use App\Domains\Payments\Exceptions\PaymentIdempotencyReplayException;
use App\Domains\Payments\Models\PaymentIdempotencyRecord;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;
use Illuminate\Database\QueryException;

final class PaymentIdempotencyService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
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
    public function begin(OrderActorData $actor, array $payload): ?PaymentIdempotencyRecord
    {
        if ($actor->idempotencyKey === null || $actor->endpoint === null) {
            return null;
        }

        $scope = $this->scope($actor);
        $hash = $this->payloadHash($payload);
        $existing = PaymentIdempotencyRecord::query()
            ->where('scope', $scope)
            ->where('idempotency_key', $actor->idempotencyKey)
            ->first();

        if ($existing !== null) {
            if ($existing->payload_hash !== $hash) {
                throw new PaymentIdempotencyConflictException;
            }
            if ($existing->status_code !== null && is_array($existing->response_body)) {
                $this->logger->info('idempotency_replay', [
                    'endpoint' => $actor->endpoint,
                    'scope' => $scope,
                ]);
                throw new PaymentIdempotencyReplayException($existing->status_code, $existing->response_body);
            }

            return $existing;
        }

        try {
            return PaymentIdempotencyRecord::query()->create([
                'scope' => $scope,
                'idempotency_key' => $actor->idempotencyKey,
                'payload_hash' => $hash,
                'endpoint' => $actor->endpoint,
                'expires_at' => $this->clock->now()->addHours((int) config('payments.idempotency_ttl_hours', 24)),
            ]);
        } catch (QueryException) {
            $existing = PaymentIdempotencyRecord::query()
                ->where('scope', $scope)
                ->where('idempotency_key', $actor->idempotencyKey)
                ->first();
            if ($existing === null) {
                throw new PaymentIdempotencyConflictException;
            }
            if ($existing->payload_hash !== $hash) {
                throw new PaymentIdempotencyConflictException;
            }
            if ($existing->status_code !== null && is_array($existing->response_body)) {
                throw new PaymentIdempotencyReplayException($existing->status_code, $existing->response_body);
            }

            return $existing;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function complete(PaymentIdempotencyRecord $record, array $body, int $statusCode): void
    {
        $record->status_code = $statusCode;
        $record->response_body = $body;
        $record->save();
    }
}
