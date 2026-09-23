<?php

declare(strict_types=1);

use App\Domains\Checkout\Support\CheckoutPhoneNormalizer;
use App\Domains\Checkout\Support\QuoteFingerprint;

it('normalizes Georgian mobiles to E.164', function (): void {
    expect(CheckoutPhoneNormalizer::normalize('555123456'))->toBe('+995555123456')
        ->and(CheckoutPhoneNormalizer::normalize('0555123456'))->toBe('+995555123456')
        ->and(CheckoutPhoneNormalizer::normalize('+995 555 123 456'))->toBe('+995555123456')
        ->and(CheckoutPhoneNormalizer::normalize('+48123456789'))->toBe('+48123456789');
});

it('builds a deterministic quote fingerprint', function (): void {
    $payload = ['cart_version' => 1, 'lines' => [['variant_id' => 2, 'quantity' => 1]]];
    $a = QuoteFingerprint::hash($payload);
    $b = QuoteFingerprint::hash(['lines' => [['quantity' => 1, 'variant_id' => 2]], 'cart_version' => 1]);

    expect($a)->toBe($b)
        ->and(QuoteFingerprint::publicValue($a))->toHaveLength(32);
});
