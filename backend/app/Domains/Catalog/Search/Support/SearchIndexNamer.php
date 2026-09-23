<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Search\Support;

use App\Domains\Catalog\Search\Enums\SearchIndexType;
use App\Domains\Catalog\Support\CatalogLocales;
use InvalidArgumentException;

final class SearchIndexNamer
{
    public function prefix(): string
    {
        $prefix = (string) config('search.index_prefix', 'outdoor_local');
        $env = (string) config('app.env', 'local');
        if ($env === 'testing' && ! str_contains($prefix, 'test')) {
            $prefix = $prefix.'_test';
        }

        return preg_replace('/[^a-z0-9_]+/i', '_', $prefix) ?: 'outdoor_local';
    }

    public function schemaVersion(): string
    {
        return (string) config('search.schema_version', 'v1');
    }

    public function uid(SearchIndexType $type, string $locale, ?string $version = null): string
    {
        $this->assertLocale($locale);
        $version ??= $this->schemaVersion();

        return strtolower($this->prefix().'_'.$type->value.'_'.$locale.'_'.$version);
    }

    public function rebuildUid(SearchIndexType $type, string $locale): string
    {
        return $this->uid($type, $locale).'_rebuild_'.substr(bin2hex(random_bytes(4)), 0, 8);
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return CatalogLocales::all();
    }

    private function assertLocale(string $locale): void
    {
        if (! CatalogLocales::isSupported($locale)) {
            throw new InvalidArgumentException("Unsupported search locale [{$locale}].");
        }
    }
}
