<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\PaymentException;
use App\Domains\Payments\Providers\BankOfGeorgia\BankOfGeorgiaMoneySerializer;

$money = new BankOfGeorgiaMoneySerializer;

it('converts minor units to exact two-decimal major strings without floats', function () use ($money): void {
    expect($money->toMajorString(1))->toBe('0.01')
        ->and($money->toMajorString(10))->toBe('0.10')
        ->and($money->toMajorString(100))->toBe('1.00')
        ->and($money->toMajorString(10050))->toBe('100.50')
        ->and($money->toMajorString(10_000_000))->toBe('100000.00');
});

it('converts provider major-unit values back to minor units exactly', function () use ($money): void {
    expect($money->fromMajor('0.01'))->toBe(1)
        ->and($money->fromMajor('0.10'))->toBe(10)
        ->and($money->fromMajor('0.1'))->toBe(10)
        ->and($money->fromMajor('1.00'))->toBe(100)
        ->and($money->fromMajor('1'))->toBe(100)
        ->and($money->fromMajor('100.50'))->toBe(10050)
        ->and($money->fromMajor('100.5'))->toBe(10050)
        ->and($money->fromMajor(2))->toBe(200);
});

it('rejects negative amounts and unsupported precision', function () use ($money): void {
    expect(fn () => $money->toMajorString(-1))->toThrow(PaymentException::class);
    expect(fn () => $money->fromMajor('-1.00'))->toThrow(PaymentException::class);
    expect(fn () => $money->fromMajor('1.001'))->toThrow(PaymentException::class);
    expect(fn () => $money->fromMajor('abc'))->toThrow(PaymentException::class);
});
