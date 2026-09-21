<?php

declare(strict_types=1);

namespace App\Domains\Catalog\PublicApi\Services;

use App\Domains\Catalog\PublicApi\Data\PublicCatalogContextData;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Inventory\Contracts\PublicInventoryAvailability;
use App\Domains\Pricing\Contracts\PublicCatalogPricing;
use App\Domains\Shared\Support\Clock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PublicCatalogCache
{
    public function __construct(
        private readonly CatalogCache $catalogCache,
        private readonly PublicCatalogPricing $pricing,
        private readonly PublicInventoryAvailability $inventory,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function remember(PublicCatalogContextData $context, string $resource, array $params, callable $callback): mixed
    {
        $key = $this->key($context, $resource, $params);
        $ttl = $this->ttlSeconds($context);
        $lockKey = $key.':lock';
        $lockSeconds = (int) config('catalog.public.cache.lock_seconds', 10);

        $hit = Cache::get($key);
        if ($hit !== null) {
            $this->log('hit', $resource, $key, $ttl);

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

            $payload = $callback();
            $this->log('miss_unlocked', $resource, $key, $ttl);

            return $payload;
        }

        try {
            $hit = Cache::get($key);
            if ($hit !== null) {
                $this->log('hit', $resource, $key, $ttl);

                return is_array($hit) && array_key_exists('payload', $hit) ? $hit['payload'] : $hit;
            }

            $payload = $callback();
            Cache::put($key, ['payload' => $payload], $ttl);
            $this->log('miss', $resource, $key, $ttl);

            return $payload;
        } finally {
            if (isset($lock)) {
                $lock->release();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function key(PublicCatalogContextData $context, string $resource, array $params): string
    {
        $normalized = $this->normalize($params);
        $hash = hash('sha256', (string) json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        ));
        $prefix = (string) config('catalog.public.cache.prefix', 'public-catalog');

        return implode(':', [
            'v1',
            $prefix,
            'c'.$this->catalogCache->version(),
            'p'.$this->pricing->cacheVersion(),
            'i'.$this->inventory->cacheVersion(),
            $context->locale,
            $context->currency,
            $resource,
            $hash,
        ]);
    }

    public function ttlSeconds(PublicCatalogContextData $context): int
    {
        $configured = max(1, (int) config('catalog.public.cache.ttl_seconds', 60));
        $boundary = $this->pricing->nextBoundaryAt($context->effectiveAt);
        if ($boundary === null) {
            return $configured;
        }

        $until = $boundary->getTimestamp() - $this->clock->now()->getTimestamp();

        return max(1, min($configured, $until));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function normalize(array $params): array
    {
        ksort($params);
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = $this->normalize($value);
            }
        }

        return $params;
    }

    private function log(string $status, string $resource, string $key, int $ttl): void
    {
        Log::info('public_catalog.cache', [
            'module' => 'catalog',
            'action' => 'public_cache',
            'endpoint' => $resource,
            'cache_status' => $status,
            'ttl' => $ttl,
            'key_fingerprint' => substr(hash('sha256', $key), 0, 16),
        ]);
    }
}
