<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Support;

use Illuminate\Support\Str;

/**
 * Stable public slugs. Display-name changes never rewrite a published slug.
 */
final class SpeciesSlug
{
    public static function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/\s+/u', '-', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;

        return trim($value, '-');
    }

    public static function isValid(string $slug): bool
    {
        if ($slug === '' || mb_strlen($slug) > 191) {
            return false;
        }

        return (bool) preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', $slug);
    }

    public static function fromScientificName(string $scientificName): string
    {
        $normalized = self::normalize($scientificName);

        return $normalized !== '' ? $normalized : 'species-'.Str::lower(Str::random(8));
    }
}
