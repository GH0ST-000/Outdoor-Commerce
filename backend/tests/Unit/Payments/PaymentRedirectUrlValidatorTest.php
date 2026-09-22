<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Support\PaymentRedirectUrlValidator;

it('accepts allowlisted https-or-http hosts and rejects malicious urls', function (): void {
    config()->set('payments.allowed_redirect_hosts', ['localhost', 'pay.example']);
    config()->set('payments.https_required', false);
    config()->set('payments.allowed_redirect_schemes', ['https', 'http']);
    $validator = new PaymentRedirectUrlValidator;

    expect($validator->validate('http://localhost/payment/test/abc'))->toBe('http://localhost/payment/test/abc');

    expect(fn () => $validator->validate('javascript:alert(1)'))->toThrow(PaymentException::class);
    expect(fn () => $validator->validate('data:text/html,hi'))->toThrow(PaymentException::class);
    expect(fn () => $validator->validate("http://localhost/pay\n"))->toThrow(PaymentException::class);
    expect(fn () => $validator->validate('http://evil.example/steal'))->toThrow(PaymentException::class);
    expect(fn () => $validator->validate('https://pay.example/'.str_repeat('a', 3000)))->toThrow(PaymentException::class);
});

it('requires https when configured', function (): void {
    config()->set('payments.allowed_redirect_hosts', ['pay.example']);
    config()->set('payments.https_required', true);
    config()->set('payments.allowed_redirect_schemes', ['https', 'http']);

    expect(fn () => (new PaymentRedirectUrlValidator)->validate('http://pay.example/pay'))
        ->toThrow(PaymentException::class);
});
