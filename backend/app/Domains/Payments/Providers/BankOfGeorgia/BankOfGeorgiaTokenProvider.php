<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Exceptions\PaymentProviderTimeoutException;
use App\Domains\Payments\Providers\BankOfGeorgia\Data\BankOfGeorgiaAccessTokenData;
use App\Domains\Payments\Support\PaymentLogger;
use App\Domains\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class BankOfGeorgiaTokenProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly Clock $clock,
        private readonly PaymentLogger $logger,
        private readonly BankOfGeorgiaConfigurationValidator $config,
    ) {}

    public function accessToken(): BankOfGeorgiaAccessTokenData
    {
        $this->config->assertReady();
        $cached = $this->readCache();
        if ($cached instanceof BankOfGeorgiaAccessTokenData) {
            $this->logger->info('bog_oauth_cache_hit', ['provider' => 'bog']);

            return $cached;
        }

        $this->logger->info('bog_oauth_cache_miss', ['provider' => 'bog']);

        try {
            $lock = Cache::lock($this->lockKey(), 10);
            $lock->block(5);
            try {
                $cached = $this->readCache();
                if ($cached instanceof BankOfGeorgiaAccessTokenData) {
                    return $cached;
                }

                return $this->fetchAndStore();
            } finally {
                $lock->release();
            }
        } catch (LockTimeoutException) {
            $cached = $this->readCache();
            if ($cached instanceof BankOfGeorgiaAccessTokenData) {
                return $cached;
            }

            return $this->fetchAndStore();
        } catch (Throwable) {
            return $this->fetchAndStore();
        }
    }

    public function forget(): void
    {
        $this->cache->forget($this->cacheKey());
    }

    private function fetchAndStore(): BankOfGeorgiaAccessTokenData
    {
        $token = $this->requestToken();
        $skew = max(0, (int) config('payments.providers.bog.token_refresh_skew_seconds', 60));
        $ttl = max(1, $token->expiresAt->getTimestamp() - $this->clock->now()->getTimestamp() - $skew);
        $this->cache->put($this->cacheKey(), [
            'token_type' => $token->tokenType,
            'expires_at' => $token->expiresAt->toIso8601String(),
            'access_token' => $token->accessToken,
        ], $ttl);

        $this->logger->info('bog_oauth_success', [
            'provider' => 'bog',
            'expires_at' => $token->expiresAt->toIso8601String(),
        ]);

        return $token;
    }

    private function requestToken(): BankOfGeorgiaAccessTokenData
    {
        $clientId = (string) config('payments.providers.bog.client_id');
        $clientSecret = (string) config('payments.providers.bog.client_secret');
        $url = (string) config('payments.providers.bog.oauth_url');
        $connect = (float) config('payments.providers.bog.connect_timeout_seconds', 5);
        $timeout = (float) config('payments.providers.bog.request_timeout_seconds', 15);

        try {
            $response = $this->http
                ->asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->connectTimeout($connect)
                ->timeout($timeout)
                ->retry(2, 100, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->post($url, ['grant_type' => 'client_credentials']);
        } catch (ConnectionException) {
            $this->logger->warning('bog_oauth_timeout', ['provider' => 'bog']);
            throw new PaymentProviderTimeoutException;
        }

        if ($response->status() === 401 || $response->status() === 403) {
            $this->logger->warning('bog_oauth_failure', ['provider' => 'bog', 'http_status' => $response->status()]);
            throw PaymentException::authenticationFailed();
        }

        if ($response->failed()) {
            $this->logger->warning('bog_oauth_failure', ['provider' => 'bog', 'http_status' => $response->status()]);
            throw PaymentException::providerUnavailable();
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = $response->json();
        } catch (Throwable) {
            throw PaymentException::malformedProviderResponse();
        }

        $access = $payload['access_token'] ?? null;
        $type = $payload['token_type'] ?? 'Bearer';
        if (! is_string($access) || $access === '' || ! is_string($type) || $type === '') {
            throw PaymentException::malformedProviderResponse();
        }

        return new BankOfGeorgiaAccessTokenData(
            accessToken: $access,
            tokenType: $type,
            expiresAt: $this->expiresAt($payload['expires_in'] ?? null),
        );
    }

    private function expiresAt(mixed $expiresIn): CarbonImmutable
    {
        $now = $this->clock->now();
        if (! is_numeric($expiresIn)) {
            return $now->addSeconds(300);
        }

        $value = (int) $expiresIn;
        if ($value <= 0) {
            return $now->addSeconds(60);
        }

        if ($value > 1_000_000_000_000) {
            return CarbonImmutable::createFromTimestampMs($value);
        }

        if ($value > 1_000_000_000) {
            return CarbonImmutable::createFromTimestamp($value);
        }

        return $now->addSeconds($value);
    }

    private function readCache(): ?BankOfGeorgiaAccessTokenData
    {
        $cached = $this->cache->get($this->cacheKey());
        if (! is_array($cached) || ! is_string($cached['access_token'] ?? null) || $cached['access_token'] === '') {
            return null;
        }

        $expiresAt = isset($cached['expires_at']) && is_string($cached['expires_at'])
            ? CarbonImmutable::parse($cached['expires_at'])
            : null;
        if ($expiresAt === null) {
            return null;
        }

        $token = new BankOfGeorgiaAccessTokenData(
            accessToken: $cached['access_token'],
            tokenType: is_string($cached['token_type'] ?? null) ? $cached['token_type'] : 'Bearer',
            expiresAt: $expiresAt,
        );

        $skew = max(0, (int) config('payments.providers.bog.token_refresh_skew_seconds', 60));
        if (! $token->isUsable($this->clock->now(), $skew)) {
            return null;
        }

        return $token;
    }

    private function cacheKey(): string
    {
        $environment = (string) config('payments.providers.bog.environment', 'test');

        return 'payments:oauth:bog:'.$environment;
    }

    private function lockKey(): string
    {
        return $this->cacheKey().':lock';
    }
}
