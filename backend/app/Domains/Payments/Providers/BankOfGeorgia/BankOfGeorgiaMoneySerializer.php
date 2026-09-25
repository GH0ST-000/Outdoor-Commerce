<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\BankOfGeorgia;

use App\Domains\Payments\Exceptions\PaymentException;

/**
 * Exact minor-unit <-> two-decimal major-unit conversion for Bank of Georgia.
 *
 * Internal money stays integer tetri. Provider JSON uses decimal major units
 * without binary floating-point arithmetic.
 */
final class BankOfGeorgiaMoneySerializer
{
    public const SCALE = 100;

    public function toMajorString(int $minor): string
    {
        if ($minor < 0) {
            throw PaymentException::moneyInvalid();
        }

        $whole = intdiv($minor, self::SCALE);
        $fraction = $minor % self::SCALE;

        return sprintf('%d.%02d', $whole, $fraction);
    }

    public function toJsonLiteral(int $minor): string
    {
        return $this->toMajorString($minor);
    }

    public function fromMajor(mixed $value): int
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw PaymentException::moneyInvalid();
            }

            return $value * self::SCALE;
        }

        if (is_float($value)) {
            $encoded = json_encode($value);
            if (! is_string($encoded) || str_contains(strtolower($encoded), 'e')) {
                throw PaymentException::moneyInvalid();
            }

            return $this->fromMajor($encoded);
        }

        if (! is_string($value)) {
            throw PaymentException::moneyInvalid();
        }

        $raw = trim((string) $value);
        if ($raw === '' || preg_match('/^-/', $raw) === 1) {
            throw PaymentException::moneyInvalid();
        }

        if (preg_match('/^\d+\.\d{3,}$/', $raw) === 1) {
            throw PaymentException::moneyInvalid();
        }

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $raw, $matches) !== 1) {
            throw PaymentException::moneyInvalid();
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '0', 2, '0');

        return ($whole * self::SCALE) + (int) $fraction;
    }
}
