<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Payments\Support\TestPaymentSignature;
use Illuminate\Testing\TestResponse;

final class PaymentFixtures
{
    /**
     * @return array{cookie: string, order_cookie: string, order_id: string, grand_total_minor: int, currency: string}
     */
    public static function pendingGuestOrder(int $variantId, string $prefix = 'pay'): array
    {
        $quoted = OrderFixtures::quotedGuestCheckout($variantId, 1, $prefix);
        $created = test()->withUnencryptedCookie((string) config('cart.cookie.name'), $quoted['cookie'])
            ->withHeaders(OrderFixtures::headers($prefix.'-ord'))
            ->postJson('/api/v1/orders', [
                'checkout_session_id' => $quoted['session_id'],
                'quote_id' => $quoted['quote_id'],
                'checkout_version' => $quoted['checkout_version'],
            ])->assertCreated();

        return [
            'cookie' => $quoted['cookie'],
            'order_cookie' => (string) OrderFixtures::guestOrderCookie($created),
            'order_id' => (string) $created->json('data.id'),
            'grand_total_minor' => (int) $created->json('data.totals.grand_total_minor'),
            'currency' => (string) $created->json('data.totals.currency'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{body: string, headers: array<string, string>}
     */
    public static function signedWebhook(array $payload, ?int $timestamp = null, ?string $secret = null): array
    {
        $body = (string) json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $secret ??= (string) config('payments.test.secret');
        $signature = (new TestPaymentSignature)->sign($body, $timestamp, $secret);

        return [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Test-Signature' => $signature,
                'X-Test-Timestamp' => (string) $timestamp,
            ],
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function postWebhook(string $provider, string $body, array $headers): TestResponse
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            if ($key === 'CONTENT_TYPE') {
                $server['CONTENT_TYPE'] = $value;
            } else {
                $server['HTTP_'.$key] = $value;
            }
        }

        return test()->call('POST', '/api/v1/payments/webhooks/'.$provider, [], [], [], $server, $body);
    }
}
