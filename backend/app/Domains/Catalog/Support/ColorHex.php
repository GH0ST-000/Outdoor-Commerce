<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Support;

/**
 * Normalizes swatch colors to `#RRGGBB` uppercase. Accepts 3- or 6-digit hex,
 * with or without the leading hash.
 */
final class ColorHex
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = strtoupper(trim($raw));
        if ($trimmed === '') {
            return null;
        }

        $digits = ltrim($trimmed, '#');

        if (preg_match('/^[0-9A-F]{3}$/', $digits) === 1) {
            $digits = $digits[0].$digits[0].$digits[1].$digits[1].$digits[2].$digits[2];
        }

        if (preg_match('/^[0-9A-F]{6}$/', $digits) !== 1) {
            return null;
        }

        return '#'.$digits;
    }

    public static function isValid(?string $raw): bool
    {
        return $raw === null || $raw === '' || self::normalize($raw) !== null;
    }
}
