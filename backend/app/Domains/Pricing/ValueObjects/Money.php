<?php

declare(strict_types=1);

namespace App\Domains\Pricing\ValueObjects;

use App\Domains\Pricing\Exceptions\CurrencyMismatchException;
use App\Domains\Pricing\Exceptions\InvalidMoneyAmountException;
use JsonSerializable;

/**
 * Immutable money in integer minor units.
 *
 * Rounding for percentage discounts: half-up to the nearest minor unit
 * (PHP_ROUND_HALF_UP). Formula: round(amount_minor × basis_points / 10000).
 */
final readonly class Money implements JsonSerializable
{
    public const MAX_AMOUNT_MINOR = 9_999_999_999_999;

    private function __construct(
        public int $amountMinor,
        public string $currencyCode,
    ) {}

    public static function of(int $amountMinor, string $currencyCode): self
    {
        if ($amountMinor < 0) {
            throw new InvalidMoneyAmountException('Money amount cannot be negative.');
        }

        if ($amountMinor > self::MAX_AMOUNT_MINOR) {
            throw new InvalidMoneyAmountException('Money amount exceeds maximum supported value.');
        }

        $code = strtoupper(trim($currencyCode));
        if ($code === '' || strlen($code) !== 3 || ! ctype_alpha($code)) {
            throw new InvalidMoneyAmountException('Currency code must be a 3-letter ISO code.');
        }

        return new self($amountMinor, $code);
    }

    public static function zero(string $currencyCode): self
    {
        return self::of(0, $currencyCode);
    }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currencyCode === $other->currencyCode;
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor > $other->amountMinor;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor < $other->amountMinor;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor >= $other->amountMinor;
    }

    public function lessThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor <= $other->amountMinor;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        $sum = $this->amountMinor + $other->amountMinor;
        if ($sum < 0 || $sum > self::MAX_AMOUNT_MINOR) {
            throw new InvalidMoneyAmountException('Addition result is out of range.');
        }

        return self::of($sum, $this->currencyCode);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        $diff = $this->amountMinor - $other->amountMinor;
        if ($diff < 0) {
            throw new InvalidMoneyAmountException('Subtraction would produce a negative amount.');
        }

        return self::of($diff, $this->currencyCode);
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor <= $other->amountMinor ? $this : $other;
    }

    public function max(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor >= $other->amountMinor ? $this : $other;
    }

    /**
     * Percentage discount using basis points (100 = 1%).
     */
    public function percentageDiscount(int $basisPoints, ?int $maximumDiscountMinor = null): self
    {
        if ($basisPoints < 1 || $basisPoints > 10_000) {
            throw new InvalidMoneyAmountException('Basis points must be between 1 and 10000.');
        }

        $product = $this->amountMinor * $basisPoints;
        $discount = (int) round($product / 10_000, 0, PHP_ROUND_HALF_UP);

        if ($maximumDiscountMinor !== null) {
            if ($maximumDiscountMinor < 0) {
                throw new InvalidMoneyAmountException('Maximum discount cannot be negative.');
            }
            $discount = min($discount, $maximumDiscountMinor);
        }

        $discount = min($discount, $this->amountMinor);

        return self::of($discount, $this->currencyCode);
    }

    /**
     * Fixed discount capped at current amount (never negative remainder).
     */
    public function fixedDiscount(int $fixedAmountMinor): self
    {
        if ($fixedAmountMinor < 1) {
            throw new InvalidMoneyAmountException('Fixed discount must be positive.');
        }

        return self::of(min($fixedAmountMinor, $this->amountMinor), $this->currencyCode);
    }

    public function applyDiscount(self $discount): self
    {
        return $this->subtract($discount);
    }

    /**
     * @return array{amount_minor: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currencyCode,
        ];
    }

    /**
     * @return array{amount_minor: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currencyCode !== $other->currencyCode) {
            throw new CurrencyMismatchException(
                "Cannot operate on {$this->currencyCode} and {$other->currencyCode}."
            );
        }
    }
}
