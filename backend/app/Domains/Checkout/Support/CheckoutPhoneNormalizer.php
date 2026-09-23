<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Support;

/**
 * Normalizes phone numbers for checkout storage.
 *
 * Georgian mobiles are stored as E.164 (`+9955XXXXXXXX`). Other international
 * numbers that already look like E.164 are preserved. Display formatting is
 * a presentation concern.
 */
final class CheckoutPhoneNormalizer
{
    public static function normalize(?string $phone): string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return '';
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '995') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10 && str_starts_with($digits, '05')) {
            return '+995'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '+995'.$digits;
        }

        if ($hasPlus && strlen($digits) >= 8 && strlen($digits) <= 15) {
            return '+'.$digits;
        }

        if (strlen($digits) >= 8 && strlen($digits) <= 15) {
            return '+'.$digits;
        }

        return $hasPlus ? '+'.$digits : $digits;
    }
}
