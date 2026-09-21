<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Support;

final class CatalogLocales
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        /** @var list<string> $locales */
        $locales = config('catalog.locales', ['ka', 'en']);

        return $locales;
    }

    public static function default(): string
    {
        return (string) config('catalog.default_locale', 'ka');
    }

    public static function fallback(): string
    {
        return (string) config('catalog.fallback_locale', 'ka');
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::all(), true);
    }

    /**
     * Storefront paths are locale-agnostic, so slug lookup accepts every supported locale.
     *
     * @return list<string>
     */
    public static function slugLookupLocales(?string $requested = null): array
    {
        $ordered = array_values(array_filter([
            $requested,
            self::fallback(),
            ...self::all(),
        ], static fn (?string $locale): bool => is_string($locale) && $locale !== ''));

        return array_values(array_unique($ordered));
    }
}
