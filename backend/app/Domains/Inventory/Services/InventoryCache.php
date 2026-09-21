<?php

declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Optional availability cache versioning. MySQL remains authoritative.
 */
final class InventoryCache
{
    public function version(): int
    {
        return (int) Cache::get($this->versionKey(), 1);
    }

    public function bumpGlobal(): int
    {
        $key = $this->versionKey();
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        return (int) Cache::increment($key);
    }

    public function bumpAvailability(int $warehouseId, int $variantId): void
    {
        $ttl = (int) config('inventory.cache.availability_ttl_seconds', 30);
        Cache::forget($this->availabilityKey($warehouseId, $variantId));
        Cache::put($this->availabilityKey($warehouseId, $variantId), $this->version(), $ttl);
    }

    public function availabilityKey(int $warehouseId, int $variantId): string
    {
        return sprintf('inventory:availability:%d:%d:%d', $this->version(), $warehouseId, $variantId);
    }

    private function versionKey(): string
    {
        return (string) config('inventory.cache.version_key', 'inventory:cache_version');
    }
}
