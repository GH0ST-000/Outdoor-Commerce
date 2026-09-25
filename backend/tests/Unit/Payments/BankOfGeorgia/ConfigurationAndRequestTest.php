<?php

declare(strict_types=1);

use App\Domains\Payments\DTOs\CreateProviderPaymentRequestData;
use App\Domains\Payments\DTOs\ProviderPaymentBasketItemData;
use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Providers\BankOfGeorgia\BankOfGeorgiaConfigurationValidator;
use App\Domains\Payments\Providers\BankOfGeorgia\BankOfGeorgiaRequestFactory;
use App\Domains\Payments\Services\PaymentProviderRegistry;
use Tests\Support\BogPaymentFixtures;

it('does not require credentials while Bank of Georgia is disabled', function (): void {
    config()->set('payments.providers.bog.enabled', false);
    config()->set('payments.providers.bog.client_id', '');
    config()->set('payments.providers.bog.client_secret', '');
    expect(app(BankOfGeorgiaConfigurationValidator::class)->issues())->toBe(['provider_disabled'])
        ->and(app(BankOfGeorgiaConfigurationValidator::class)->isReady())->toBeFalse();
});

it('requires credentials, HTTPS hosts, and an allowed payment method when enabled', function (): void {
    BogPaymentFixtures::enable();
    expect(app(BankOfGeorgiaConfigurationValidator::class)->isReady())->toBeTrue();

    config()->set('payments.providers.bog.client_secret', '');
    expect(app(BankOfGeorgiaConfigurationValidator::class)->isReady())->toBeFalse();

    BogPaymentFixtures::enable();
    config()->set('payments.providers.bog.api_base_url', 'https://evil.example/payments');
    expect(app(BankOfGeorgiaConfigurationValidator::class)->issues())->toContain('host_not_allowed');

    BogPaymentFixtures::enable();
    config()->set('payments.providers.bog.allowed_methods', ['google_pay']);
    expect(implode(' ', app(BankOfGeorgiaConfigurationValidator::class)->issues()))->toContain('unsupported_payment_method');
});

it('does not silently replace Bank of Georgia with the test provider', function (): void {
    BogPaymentFixtures::enable();
    $provider = app(PaymentProviderRegistry::class)->resolve('bog');
    expect($provider->code())->toBe('bog');
});

it('encodes GEL amounts as exact JSON numbers and rejects a basket that does not reconcile', function (): void {
    BogPaymentFixtures::enable();
    $factory = app(BankOfGeorgiaRequestFactory::class);
    $ok = $factory->createOrder(new CreateProviderPaymentRequestData(
        merchantReference: '11111111-1111-1111-1111-111111111111',
        amountMinor: 10050,
        currency: 'GEL',
        description: 'ORD-1',
        returnUrl: 'http://localhost:3000/payment/return',
        callbackUrl: 'http://localhost:8000/api/v1/payments/webhooks/bog',
        customerLocale: 'ka',
        customerEmail: null,
        customerPhone: null,
        metadata: [],
        basketItems: [
            new ProviderPaymentBasketItemData('item-1', 'Scope', 1, 10050, 0, 10050),
        ],
        reservationExpiresAt: now()->addMinutes(15)->toImmutable(),
        providerIdempotencyKey: '11111111-1111-1111-1111-111111111111',
    ));
    expect($ok['json'])->toContain('"total_amount":100.50')
        ->and($ok['headers']['Accept-Language'])->toBe('ka')
        ->and($ok['headers']['Idempotency-Key'])->toBe('11111111-1111-1111-1111-111111111111')
        ->and($ok['json'])->toContain('"capture":"automatic"')
        ->and($ok['json'])->toContain('"payment_method":["card"]')
        ->and($ok['json'])->not->toContain('google_pay');

    expect(fn () => $factory->createOrder(new CreateProviderPaymentRequestData(
        merchantReference: '11111111-1111-1111-1111-111111111111',
        amountMinor: 10050,
        currency: 'GEL',
        description: 'ORD-1',
        returnUrl: 'http://localhost:3000/payment/return',
        callbackUrl: 'http://localhost:8000/api/v1/payments/webhooks/bog',
        customerLocale: 'en',
        customerEmail: null,
        customerPhone: null,
        metadata: [],
        basketItems: [
            new ProviderPaymentBasketItemData('item-1', 'Scope', 1, 10000, 0, 10000),
        ],
        reservationExpiresAt: now()->addMinutes(15)->toImmutable(),
        providerIdempotencyKey: '11111111-1111-1111-1111-111111111111',
    )))->toThrow(PaymentException::class);
});
