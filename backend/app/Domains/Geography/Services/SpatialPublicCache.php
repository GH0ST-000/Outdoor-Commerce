<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SpatialPublicCache
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function remember(string $resource, array $params, callable $callback): mixed
    {
        $key = $this->key($resource, $params);
        $ttl = (int) config('spatial.cache.ttl_seconds', 120);
        $hit = Cache::get($key);
        if ($hit !== null) {
            return is_array($hit) && array_key_exists('payload', $hit) ? $hit['payload'] : $hit;
        }

        $payload = $callback();
        Cache::put($key, ['payload' => $payload], $ttl);

        return $payload;
    }

    public function bump(): void
    {
        try {
            $current = (int) Cache::get($this->versionKey(), 1);
            Cache::forever($this->versionKey(), $current + 1);
        } catch (Throwable $exception) {
            Log::warning('spatial.cache_invalidation_failed', ['message' => $exception->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function key(string $resource, array $params): string
    {
        $version = (int) Cache::get($this->versionKey(), 1);
        ksort($params);

        return 'spatial:public:'.$version.':'.$resource.':'.hash('sha256', json_encode($params) ?: '');
    }

    private function versionKey(): string
    {
        return (string) config('spatial.cache.version_key', 'spatial:public:version');
    }
}
