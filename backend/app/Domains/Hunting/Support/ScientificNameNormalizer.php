<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Support;

/**
 * Scientific names are stored as Latin binomials (or trinomials for subspecies).
 * Uniqueness is compared on the NFC-normalized, whitespace-collapsed, case-folded value.
 */
final class ScientificNameNormalizer
{
    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $nfc = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($nfc) && $nfc !== '') {
                $value = $nfc;
            }
        }

        $collapsed = preg_replace('/\s+/u', ' ', $value);
        $value = is_string($collapsed) ? $collapsed : $value;

        return mb_strtolower(trim($value), 'UTF-8');
    }

    public static function display(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $value) ?: [];
        if ($parts === []) {
            return $value;
        }

        $genus = mb_convert_case($parts[0], MB_CASE_TITLE, 'UTF-8');
        $rest = array_map(
            static fn (string $part): string => mb_strtolower($part, 'UTF-8'),
            array_slice($parts, 1),
        );

        return trim($genus.' '.implode(' ', $rest));
    }

    public static function isValidBinomial(string $value): bool
    {
        return (bool) preg_match(
            '/^[A-Z][a-z]+ [a-z]+(?: [a-z]+)?$/',
            self::display($value),
        );
    }

    public static function genus(string $value): ?string
    {
        $display = self::display($value);
        $parts = preg_split('/\s+/u', $display) ?: [];

        return $parts[0] ?? null;
    }

    public static function epithet(string $value): ?string
    {
        $display = self::display($value);
        $parts = preg_split('/\s+/u', $display) ?: [];

        return $parts[1] ?? null;
    }
}
