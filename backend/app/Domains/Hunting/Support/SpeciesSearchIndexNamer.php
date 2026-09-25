<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Support;

use App\Domains\Catalog\Contracts\CatalogLocales;

final class SpeciesSearchIndexNamer
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
        return (string) config('species.search.schema_version', 'v1');
    }

    public function uid(string $locale, ?string $version = null): string
    {
        if (! CatalogLocales::isSupported($locale)) {
            throw new \InvalidArgumentException("Unsupported search locale [{$locale}].");
        }
        $version ??= $this->schemaVersion();

        return strtolower($this->prefix().'_species_'.$locale.'_'.$version);
    }

    public function rebuildUid(string $locale): string
    {
        return $this->uid($locale).'_rebuild_'.substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
