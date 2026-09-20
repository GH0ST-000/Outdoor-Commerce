<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Contracts;

use App\Domains\Catalog\Support\CatalogLocales as InternalCatalogLocales;

/**
 * Public locale catalog for other modules (admin list filters, presentation).
 */
final class CatalogLocales
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return InternalCatalogLocales::all();
    }

    public static function default(): string
    {
        return InternalCatalogLocales::default();
    }

    public static function fallback(): string
    {
        return InternalCatalogLocales::fallback();
    }

    public static function isSupported(string $locale): bool
    {
        return InternalCatalogLocales::isSupported($locale);
    }
}
