<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Exceptions\PaymentProviderTimeoutException;
use App\Domains\Payments\Providers\BankOfGeorgia\Data\BankOfGeorgiaAccessTokenData;
use App\Domains\Payments\Support\PaymentLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

final class BankOfGeorgiaHttpClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly BankOfGeorgiaTokenProvider $tokens,
        private readonly PaymentLogger $logger,
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function createOrder(string $jsonBody, array $headers): Response
    {
        return $this->sendAuthenticated(
            fn (BankOfGeorgiaAccessTokenData $token): Response => $this->http
                ->withToken($token->accessToken, $token->tokenType)
                ->withHeaders($headers)
                ->withBody($jsonBody, 'application/json')
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->requestTimeout())
                ->post($this->apiBase().'/ecommerce/orders'),
            'create_order',
        );
    }

    public function paymentDetails(string $providerOrderId): Response
    {
        $orderId = rawurlencode($providerOrderId);

        return $this->sendAuthenticated(
            fn (BankOfGeorgiaAccessTokenData $token): Response => $this->http
                ->withToken($token->accessToken, $token->tokenType)
                ->acceptJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->requestTimeout())
                ->get($this->apiBase().'/receipt/'.$orderId),
            'payment_details',
        );
    }

    /**
     * @param  callable(BankOfGeorgiaAccessTokenData): Response  $send
     */
    private function sendAuthenticated(callable $send, string $operation): Response
    {
        $started = microtime(true);
        $retriedAuth = false;
        $retriedRateLimit = false;

        while (true) {
            $token = $this->tokens->accessToken();
            try {
                $response = $send($token);
            } catch (ConnectionException) {
                $this->logger->warning('bog_'.$operation.'_timeout', [
                    'provider' => 'bog',
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                ]);
                throw new PaymentProviderTimeoutException;
            }

            $this->logger->info('bog_'.$operation.'_duration', [
                'provider' => 'bog',
                'http_status' => $response->status(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);

            if (in_array($response->status(), [401, 403], true) && ! $retriedAuth) {
                $this->tokens->forget();
                $retriedAuth = true;

                continue;
            }

            if ($response->status() === 429 && ! $retriedRateLimit) {
                $retriedRateLimit = true;

                continue;
            }

            if (in_array($response->status(), [401, 403], true)) {
                throw PaymentException::authenticationFailed();
            }

            return $response;
        }
    }

    private function apiBase(): string
    {
        return rtrim((string) config('payments.providers.bog.api_base_url'), '/');
    }

    private function connectTimeout(): float
    {
        return (float) config('payments.providers.bog.connect_timeout_seconds', 5);
    }

    private function requestTimeout(): float
    {
        return (float) config('payments.providers.bog.request_timeout_seconds', 15);
    }
}
