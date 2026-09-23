<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Orders\Actions\AssertOrderAccessAction;
use App\Domains\Orders\DTOs\OrderActorData;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\Payments\Support\PaymentWebhookHeaderAllowlist;
use App\Domains\Payments\Support\TestPaymentSignature;
use App\Domains\Shared\Support\Clock;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;

final class SimulateTestPaymentService
{
    public function __construct(
        private readonly Application $app,
        private readonly AssertOrderAccessAction $assertAccess,
        private readonly ReceivePaymentWebhookService $webhooks,
        private readonly TestPaymentSignature $signatures,
        private readonly PaymentWebhookHeaderAllowlist $headers,
        private readonly Clock $clock,
    ) {}

    public function execute(OrderActorData $actor, string $attemptPublicId, string $outcome): PaymentAttempt
    {
        if ($this->app->environment('production') || ! (bool) config('payments.test.enabled', false)) {
            throw PaymentException::simulateForbidden();
        }

        $attempt = PaymentAttempt::query()->where('public_id', $attemptPublicId)->first();
        if ($attempt === null) {
            throw PaymentException::notFound();
        }
        $order = Order::query()->whereKey($attempt->order_id)->first();
        if ($order === null) {
            throw PaymentException::notFound();
        }
        $this->assertAccess->execute($order, $actor);

        $status = match ($outcome) {
            'failure', 'failed' => 'failed',
            'cancelled' => 'cancelled',
            'processing' => 'processing',
            'amount_mismatch' => 'succeeded',
            'currency_mismatch' => 'succeeded',
            'late_success' => 'succeeded',
            default => 'succeeded',
        };

        $amount = $outcome === 'amount_mismatch' ? $attempt->amount_minor + 1 : $attempt->amount_minor;
        $currency = $outcome === 'currency_mismatch' ? 'USD' : $attempt->currency;
        $payload = (string) json_encode([
            'event_id' => 'evt_'.str_replace('-', '', (string) Str::uuid()),
            'payment_id' => $attempt->provider_payment_id ?? 'testpay_'.str_replace('-', '', $attempt->public_id),
            'transaction_id' => $attempt->provider_transaction_id,
            'merchant_reference' => $attempt->public_id,
            'event_type' => 'payment.'.$status,
            'status' => $status,
            'amount_minor' => $amount,
            'currency' => $currency,
            'occurred_at' => $this->clock->now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = $this->signatures->sign($payload, $timestamp, (string) config('payments.test.secret'));
        $headers = $this->headers->filter([
            'content-type' => 'application/json',
            'x-test-signature' => $signature,
            'x-test-timestamp' => (string) $timestamp,
        ]);

        $this->webhooks->execute($attempt->provider, $payload, $headers, 'application/json');

        return $attempt->fresh() ?? $attempt;
    }
}
