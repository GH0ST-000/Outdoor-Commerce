<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Support;

use App\Domains\Catalog\Contracts\CatalogLocales;

final class SpeciesLocales
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        /** @var list<string> $locales */
        $locales = config('species.locales', CatalogLocales::all());

        return $locales;
    }

    public static function default(): string
    {
        return (string) config('species.default_locale', CatalogLocales::default());
    }

    public static function fallback(): string
    {
        return (string) config('species.fallback_locale', CatalogLocales::fallback());
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::all(), true);
    }
}
