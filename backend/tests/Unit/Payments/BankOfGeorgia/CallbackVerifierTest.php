<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Providers\BankOfGeorgia\BankOfGeorgiaCallbackVerifier;
use Tests\Support\BogPaymentFixtures;

beforeEach(function (): void {
    BogPaymentFixtures::enable();
});

it('accepts a valid SHA256withRSA signature over the exact raw body', function (): void {
    $signed = BogPaymentFixtures::signedCallback('order-1', 'merchant-1');
    app(BankOfGeorgiaCallbackVerifier::class)->verify($signed['body'], $signed['headers']['Callback-Signature']);
    expect(true)->toBeTrue();
});

it('rejects missing, malformed, invalid, and mutated signatures', function (): void {
    $verifier = app(BankOfGeorgiaCallbackVerifier::class);
    $signed = BogPaymentFixtures::signedCallback('order-1', 'merchant-1');

    expect(fn () => $verifier->verify($signed['body'], ''))->toThrow(PaymentException::class);
    expect(fn () => $verifier->verify($signed['body'], '%%%not-base64%%%'))->toThrow(PaymentException::class);
    expect(fn () => $verifier->verify($signed['body'], base64_encode('nope')))->toThrow(PaymentException::class);
    expect(fn () => $verifier->verify($signed['body'].' ', $signed['headers']['Callback-Signature']))->toThrow(PaymentException::class);
    expect(fn () => $verifier->verify(substr($signed['body'], 0, -1), $signed['headers']['Callback-Signature']))->toThrow(PaymentException::class);
    expect(fn () => $verifier->verify('', $signed['headers']['Callback-Signature']))->toThrow(PaymentException::class);

    $reordered = (string) json_encode(array_reverse(json_decode($signed['body'], true, 512, JSON_THROW_ON_ERROR), true), JSON_THROW_ON_ERROR);
    expect(fn () => $verifier->verify($reordered, $signed['headers']['Callback-Signature']))->toThrow(PaymentException::class);
});

it('accepts the previous public key during rotation', function (): void {
    $first = BogPaymentFixtures::testKeyPair();
    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($resource, $previousPrivate);
    $previousPublic = (string) openssl_pkey_get_details($resource)['key'];
    config()->set('payments.providers.bog.callback_public_key', $first['public']);
    config()->set('payments.providers.bog.callback_public_key_previous', $previousPublic);

    $body = '{"event":"order_payment"}';
    openssl_sign($body, $binary, $previousPrivate, OPENSSL_ALGO_SHA256);
    app(BankOfGeorgiaCallbackVerifier::class)->verify($body, base64_encode($binary));
    expect(true)->toBeTrue();
});

it('rejects a signature from the wrong key', function (): void {
    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($resource, $otherPrivate);
    $signed = BogPaymentFixtures::signedCallback('order-1', 'merchant-1', privateKey: $otherPrivate);
    expect(fn () => app(BankOfGeorgiaCallbackVerifier::class)->verify($signed['body'], $signed['headers']['Callback-Signature']))
        ->toThrow(PaymentException::class);
});
