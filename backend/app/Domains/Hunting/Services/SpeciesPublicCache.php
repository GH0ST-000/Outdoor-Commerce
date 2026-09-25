<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SpeciesPublicCache
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function remember(string $locale, string $resource, array $params, callable $callback): mixed
    {
        $key = $this->key($locale, $resource, $params);
        $ttl = (int) config('species.cache.ttl_seconds', 300);
        $lockKey = $key.':lock';
        $lockSeconds = (int) config('species.cache.lock_seconds', 10);

        $hit = Cache::get($key);
        if ($hit !== null) {
            return is_array($hit) && array_key_exists('payload', $hit) ? $hit['payload'] : $hit;
        }

        try {
            $lock = Cache::lock($lockKey, $lockSeconds);
            $lock->block(5);
        } catch (Throwable) {
            $retry = Cache::get($key);
            if ($retry !== null) {
                return is_array($retry) && array_key_exists('payload', $retry) ? $retry['payload'] : $retry;
            }

            return $callback();
        }

        try {
            $hit = Cache::get($key);
            if ($hit !== null) {
                return is_array($hit) && array_key_exists('payload', $hit) ? $hit['payload'] : $hit;
            }

            $payload = $callback();
            Cache::put($key, ['payload' => $payload], $ttl);

            return $payload;
        } finally {
            if (isset($lock)) {
                $lock->release();
            }
        }
    }

    public function bump(): void
    {
        try {
            // remember() treats a missing version key as 1. Incrementing a missing
            // Redis key would also become 1 and reuse stale public payloads.
            $current = (int) Cache::get($this->versionKey(), 1);
            Cache::forever($this->versionKey(), $current + 1);
        } catch (Throwable $exception) {
            Log::warning('species.cache_invalidation_failed', [
                'message' => $exception->getMessage(),
            ]);
            Cache::forever($this->versionKey(), (int) Cache::get($this->versionKey(), 1) + 1);
        }
    }

    public function ttlSeconds(): int
    {
        return (int) config('species.cache.ttl_seconds', 300);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function key(string $locale, string $resource, array $params): string
    {
        $version = (int) Cache::get($this->versionKey(), 1);
        ksort($params);

        return 'species:public:'.$version.':'.$locale.':'.$resource.':'.hash('sha256', json_encode($params) ?: '');
    }

    private function versionKey(): string
    {
        return (string) config('species.cache.version_key', 'species:public:version');
    }
}
