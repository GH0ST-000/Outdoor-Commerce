<?php

declare(strict_types=1);

use App\Domains\Pricing\Exceptions\CurrencyMismatchException;
use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;
use App\Domains\Pricing\ValueObjects\Money;

it('stores integer minor units and rejects negatives', function (): void {
    $money = Money::of(1999, 'gel');
    expect($money->amountMinor)->toBe(1999);
    expect($money->currencyCode)->toBe('GEL');

    expect(fn () => Money::of(-1, 'GEL'))->toThrow(InvalidMoneyAmountException::class);
});

it('adds and subtracts same currency only', function (): void {
    $a = Money::of(1000, 'GEL');
    $b = Money::of(250, 'GEL');

    expect($a->add($b)->amountMinor)->toBe(1250);
    expect($a->subtract($b)->amountMinor)->toBe(750);

    expect(fn () => $a->add(Money::of(1, 'USD')))->toThrow(CurrencyMismatchException::class);
});

it('rounds percentage discounts half up to minor unit', function (): void {
    $base = Money::of(9999, 'GEL');
    $discount = $base->percentageDiscount(1500);

    expect($discount->amountMinor)->toBe(1500);
});

it('serializes to array', function (): void {
    expect(Money::of(100, 'GEL')->toArray())->toBe([
        'amount_minor' => 100,
        'currency' => 'GEL',
    ]);
});
