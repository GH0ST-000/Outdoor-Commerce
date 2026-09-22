<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Support;

final class CheckoutTextNormalizer
{
    public static function name(?string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', (string) $value);

        return trim($collapsed ?? (string) $value);
    }

    public static function email(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    public static function line(?string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', (string) $value);

        return trim($collapsed ?? (string) $value);
    }

    public static function containsMarkup(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return str_contains($value, '<') || str_contains($value, '>') || str_contains($value, "\0");
    }

    public static function containsControlCharacters(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1;
    }
}
