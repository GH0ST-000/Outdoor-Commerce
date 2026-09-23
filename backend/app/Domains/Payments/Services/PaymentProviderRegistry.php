<?php

declare(strict_types=1);

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Contracts\PaymentProvider;
use App\Domains\Payments\Enums\PaymentProviderCode;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Providers\TestHostedPaymentProvider;
use App\Domains\Payments\Support\PaymentLogger;
use Illuminate\Contracts\Foundation\Application;

final class PaymentProviderRegistry
{
    public function __construct(
        private readonly Application $app,
        private readonly PaymentLogger $logger,
    ) {}

    public function resolve(string $code): PaymentProvider
    {
        $this->assertEnvironment();

        $providers = config('payments.providers', []);
        if (! is_array($providers) || ! isset($providers[$code]) || ! is_array($providers[$code])) {
            throw PaymentException::providerUnknown();
        }

        $config = $providers[$code];
        $enabled = (bool) ($config['enabled'] ?? false);
        $productionAllowed = (bool) ($config['production_allowed'] ?? false);

        if ($code === PaymentProviderCode::Test->value) {
            if ($this->app->environment('production') || ! $enabled || $productionAllowed) {
                $this->logger->error('test_provider_blocked', ['provider' => $code]);
                throw PaymentException::testProviderForbidden();
            }
        }

        if (! $enabled) {
            throw PaymentException::providerUnavailable();
        }

        $driver = (string) ($config['driver'] ?? $code);

        return match ($driver) {
            'test' => $this->app->make(TestHostedPaymentProvider::class),
            default => throw PaymentException::providerUnknown(),
        };
    }

    public function assertEnvironment(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $testEnabled = (bool) config('payments.test.enabled', false);
        $providerEnabled = (bool) config('payments.providers.test.enabled', false);
        if ($testEnabled || $providerEnabled) {
            throw PaymentException::testProviderForbidden();
        }
    }

    /**
     * @return array{provider: string, enabled: bool, production_allowed: bool}
     */
    public function health(string $code): array
    {
        $providers = config('payments.providers', []);
        $config = is_array($providers) && isset($providers[$code]) && is_array($providers[$code])
            ? $providers[$code]
            : [];

        return [
            'provider' => $code,
            'enabled' => (bool) ($config['enabled'] ?? false),
            'production_allowed' => (bool) ($config['production_allowed'] ?? false),
        ];
    }
}
