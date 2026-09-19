<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Support;

use Illuminate\Support\Str;

/**
 * Slug helper that preserves Georgian Unicode letters.
 */
final class CatalogSlug
{
    public static function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/\s+/u', '-', $value) ?? $value;
        // Allow letters (incl. Georgian), numbers, hyphens
        $value = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;

        return trim($value, '-');
    }

    public static function isValid(string $slug): bool
    {
        if ($slug === '' || mb_strlen($slug) > 255) {
            return false;
        }

        return (bool) preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', $slug);
    }

    public static function fromName(string $name): string
    {
        $normalized = self::normalize($name);

        return $normalized !== '' ? $normalized : 'item-'.Str::lower(Str::random(6));
    }
}
