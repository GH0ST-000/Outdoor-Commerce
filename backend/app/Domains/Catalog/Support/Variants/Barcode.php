<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Support\Variants;

use InvalidArgumentException;

/**
 * Optional GTIN barcode (8/12/13/14). Digits only; check digit validated.
 */
final readonly class Barcode
{
    private function __construct(public string $value) {}

    public static function fromNullable(?string $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $digits = self::normalize($raw);
        if ($digits === null) {
            return null;
        }

        self::assertValid($digits);

        return new self($digits);
    }

    public static function normalize(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/[\s-]+/', '', $trimmed) ?? '';
        if ($digits === '') {
            return null;
        }

        return $digits;
    }

    public static function assertValid(string $digits): void
    {
        if (! ctype_digit($digits)) {
            throw new InvalidArgumentException('Barcode may only contain digits.');
        }

        $allowed = config('catalog.variants.barcode.allowed_lengths', [8, 12, 13, 14]);
        $length = strlen($digits);
        if (! in_array($length, $allowed, true)) {
            throw new InvalidArgumentException('Barcode length is not a supported GTIN format.');
        }

        if (! self::passesCheckDigit($digits)) {
            throw new InvalidArgumentException('Barcode check digit is invalid.');
        }
    }

    public static function passesCheckDigit(string $digits): bool
    {
        $length = strlen($digits);
        $sum = 0;
        // GS1: from right, odd positions ×3, even ×1 (excluding check digit).
        for ($i = 1; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];
            $sum += ($i % 2 === 1) ? $digit * 3 : $digit;
        }
        $expected = (10 - ($sum % 10)) % 10;

        return $expected === (int) $digits[$length - 1];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
