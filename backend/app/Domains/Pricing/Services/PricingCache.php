<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Services;

use Illuminate\Support\Facades\Cache;

final class PricingCache
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

    public function quoteKey(int $variantId, int $priceListId, string $currency): string
    {
        return sprintf(
            'pricing:quote:%d:%d:%s:%d',
            $this->version(),
            $variantId,
            $priceListId,
            strtoupper($currency),
        );
    }

    private function versionKey(): string
    {
        return (string) config('pricing.cache.version_key', 'pricing:cache_version');
    }
}
