<?php

declare(strict_types=1);

use App\Domains\Shipping\Exceptions\ShipmentException;
use App\Domains\Shipping\Support\TrackingNumberNormalizer;
use App\Domains\Shipping\Support\TrackingUrlValidator;

it('accepts allowlisted https tracking urls and rejects javascript data and unknown hosts', function (): void {
    config()->set('shipping.allowed_tracking_hosts', ['tracking.example.com']);
    config()->set('shipping.https_required', true);
    config()->set('shipping.allowed_tracking_schemes', ['https', 'http']);

    $validator = new TrackingUrlValidator;

    expect($validator->validate('https://tracking.example.com/TRACK-1'))->toBe('https://tracking.example.com/TRACK-1');

    expect(fn () => $validator->validate('javascript:alert(1)'))->toThrow(ShipmentException::class);
    expect(fn () => $validator->validate('data:text/html,hi'))->toThrow(ShipmentException::class);
    expect(fn () => $validator->validate('http://tracking.example.com/x'))->toThrow(ShipmentException::class);
    expect(fn () => $validator->validate('https://evil.example/x'))->toThrow(ShipmentException::class);
});

it('rejects tracking numbers with control characters', function (): void {
    $normalizer = new TrackingNumberNormalizer;

    expect($normalizer->normalize(' TRACK-1 '))->toBe('TRACK-1');
    expect(fn () => $normalizer->normalize("TRACK\n1"))->toThrow(ShipmentException::class);
});
