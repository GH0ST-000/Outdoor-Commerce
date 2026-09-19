<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Catalog public-cache version bump. Day 12 will consume this; Day 7 only increments after commit.
 */
final class CatalogCache
{
    public function version(): int
    {
        return (int) Cache::get($this->key(), 1);
    }

    public function bump(): int
    {
        $key = $this->key();
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        return (int) Cache::increment($key);
    }

    private function key(): string
    {
        return (string) config('catalog.cache.version_key', 'catalog:cache_version');
    }
}
