<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

final class BogPaymentFixtures
{
    public static ?string $detailsExternalOrderId = null;

    public static ?string $detailsAmountMajor = null;

    public static ?string $detailsCurrency = null;

    /**
     * @return array{private: string, public: string}
     */
    public static function testKeyPair(): array
    {
        static $pair = null;
        if (is_array($pair)) {
            return $pair;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        expect($resource)->not->toBeFalse();
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        expect($details)->toBeArray();

        $pair = [
            'private' => $private,
            'public' => (string) $details['key'],
        ];

        return $pair;
    }

    public static function enable(bool $withTestKeys = true): void
    {
        $keys = $withTestKeys ? self::testKeyPair() : ['public' => ''];
        config()->set('payments.providers.bog.enabled', true);
        config()->set('payments.providers.bog.client_id', 'bog-test-client');
        config()->set('payments.providers.bog.client_secret', 'bog-test-secret');
        config()->set('payments.providers.bog.oauth_url', 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token');
        config()->set('payments.providers.bog.api_base_url', 'https://api.bog.ge/payments/v1');
        config()->set('payments.providers.bog.callback_url', 'http://localhost:8000/api/v1/payments/webhooks/bog');
        config()->set('payments.providers.bog.success_url', 'http://localhost:3000/payment/return');
        config()->set('payments.providers.bog.fail_url', 'http://localhost:3000/payment/return');
        config()->set('payments.providers.bog.callback_public_key', $keys['public']);
        config()->set('payments.providers.bog.documented_callback_public_key', '');
        config()->set('payments.providers.bog.allowed_methods', ['card']);
        config()->set('payments.methods.bog_hosted_card.is_enabled', true);
        config()->set('payments.https_required', false);
        config()->set('payments.providers.bog.allowed_redirect_hosts', ['payment.bog.ge']);
    }

    public static function fakeHappyPath(string $providerOrderId = 'bog-order-test-1'): void
    {
        self::$detailsExternalOrderId = null;
        self::$detailsAmountMajor = null;
        self::$detailsCurrency = null;
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.bog.ge/*' => Http::response([
                'access_token' => 'bog-access-token-test',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api.bog.ge/payments/v1/ecommerce/orders' => Http::response(self::createOrderSuccess($providerOrderId), 200),
            'https://api.bog.ge/payments/v1/receipt/*' => function () use ($providerOrderId) {
                $amount = self::$detailsAmountMajor ?? '100.00';

                return Http::response(self::paymentDetailsCompleted($providerOrderId, [
                    'external_order_id' => self::$detailsExternalOrderId ?? 'will-set',
                    'purchase_units' => [
                        'request_amount' => $amount,
                        'transfer_amount' => $amount,
                        'currency_code' => self::$detailsCurrency ?? 'GEL',
                    ],
                ]), 200);
            },
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function createOrderSuccess(string $orderId): array
    {
        return [
            'id' => $orderId,
            '_links' => [
                'details' => ['href' => 'https://api.bog.ge/payments/v1/receipt/'.$orderId],
                'redirect' => ['href' => 'https://payment.bog.ge/?order_id='.$orderId],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function paymentDetailsCompleted(string $orderId, array $overrides = []): array
    {
        return array_replace_recursive([
            'order_id' => $orderId,
            'external_order_id' => $overrides['external_order_id'] ?? 'will-set',
            'order_status' => ['key' => 'completed', 'value' => 'completed'],
            'purchase_units' => [
                'request_amount' => '100.00',
                'transfer_amount' => '100.00',
                'currency_code' => 'GEL',
            ],
            'payment_detail' => [
                'transaction_id' => 'txn-bog-test',
                'payer_identifier' => '548888xxxxxx9893',
                'card_expiry_date' => '03/24',
                'auth_code' => '483921',
            ],
            'buyer' => [
                'full_name' => 'should-not-be-logged',
                'email' => 'hidden@example.com',
                'phone_number' => '+995555000000',
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $bodyOverrides
     * @return array{body: string, headers: array<string, string>}
     */
    public static function signedCallback(
        string $providerOrderId,
        string $merchantReference,
        string $status = 'completed',
        array $bodyOverrides = [],
        ?string $privateKey = null,
        string $requestAmount = '100.00',
        string $currency = 'GEL',
    ): array {
        $payload = [
            'event' => 'order_payment',
            'zoned_request_time' => '2026-09-23T08:00:00.000000Z',
            'body' => array_replace_recursive([
                'order_id' => $providerOrderId,
                'external_order_id' => $merchantReference,
                'order_status' => ['key' => $status, 'value' => $status],
                'purchase_units' => [
                    'request_amount' => $requestAmount,
                    'transfer_amount' => $status === 'completed' ? $requestAmount : '0.00',
                    'currency_code' => $currency,
                ],
                'payment_detail' => [
                    'transaction_id' => 'txn-bog-test',
                ],
            ], $bodyOverrides),
        ];
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $privateKey ??= self::testKeyPair()['private'];
        openssl_sign($body, $binary, $privateKey, OPENSSL_ALGO_SHA256);

        return [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Callback-Signature' => base64_encode($binary),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function postCallback(string $body, array $headers): TestResponse
    {
        return PaymentFixtures::postWebhook('bog', $body, $headers);
    }

    public static function lastCreateOrderRequest(): ?Request
    {
        return Http::recorded()
            ->map(fn (array $pair): Request => $pair[0])
            ->first(fn (Request $request): bool => str_contains($request->url(), '/ecommerce/orders'));
    }
}
