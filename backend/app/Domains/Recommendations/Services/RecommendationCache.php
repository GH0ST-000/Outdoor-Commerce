<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Recommendations\Support\RecommendationCacheKey;
use Illuminate\Support\Facades\Cache;

final class RecommendationCache
{
    public function __construct(private readonly RecommendationCacheKey $keys) {}

    public function version(): int
    {
        return (int) Cache::get($this->versionKey(), 1);
    }

    public function bump(): void
    {
        $key = $this->versionKey();
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }
        Cache::increment($key);
    }

    /**
     * @param  array<string, mixed>  $parts
     * @param  callable(): array<string, mixed>  $callback
     * @return array{hit: bool, value: array<string, mixed>}
     */
    public function remember(array $parts, callable $callback): array
    {
        $parts['version'] = $this->version();
        $key = $this->keys->make($parts);
        $ttl = (int) config('recommendations.cache.ttl_seconds', 45);
        if (Cache::has($key)) {
            /** @var array<string, mixed> $cached */
            $cached = Cache::get($key);

            return ['hit' => true, 'value' => $cached];
        }

        $lock = Cache::lock($key.':lock', (int) config('recommendations.cache.lock_seconds', 8));
        try {
            $lock->block(2);
        } catch (\Throwable) {
            $value = $callback();
            Cache::put($key, $value, $ttl);

            return ['hit' => false, 'value' => $value];
        }

        try {
            $cached = $this->stored($key);
            if ($cached !== null) {
                return ['hit' => true, 'value' => $cached];
            }
            $value = $callback();
            Cache::put($key, $value, $ttl);

            return ['hit' => false, 'value' => $value];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stored(string $key): ?array
    {
        if (! Cache::has($key)) {
            return null;
        }

        /** @var array<string, mixed> $cached */
        $cached = Cache::get($key);

        return $cached;
    }

    private function versionKey(): string
    {
        return (string) config('recommendations.cache.prefix', 'recommendations').':version';
    }
}
