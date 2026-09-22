<?php

declare(strict_types=1);

use App\Domains\Payments\Enums\PaymentProviderCode;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Providers\TestHostedPaymentProvider;
use App\Domains\Payments\Services\PaymentProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the test provider by code and rejects unknown or disabled providers', function (): void {
    $registry = app(PaymentProviderRegistry::class);
    $provider = $registry->resolve(PaymentProviderCode::Test->value);
    expect($provider)->toBeInstanceOf(TestHostedPaymentProvider::class)
        ->and($provider->code())->toBe('test');

    expect(fn () => $registry->resolve('bogus-bank'))->toThrow(PaymentException::class);

    config()->set('payments.providers.test.enabled', false);
    config()->set('payments.test.enabled', false);
    expect(fn () => $registry->resolve('test'))->toThrow(PaymentException::class);
});

it('refuses to enable the test provider in production', function (): void {
    $this->app['env'] = 'production';
    config()->set('app.env', 'production');
    config()->set('payments.test.enabled', true);
    config()->set('payments.providers.test.enabled', true);

    expect(fn () => app(PaymentProviderRegistry::class)->resolve('test'))
        ->toThrow(PaymentException::class);
});
