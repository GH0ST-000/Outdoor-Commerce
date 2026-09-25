<?php

declare(strict_types=1);

namespace Tests;

use App\Domains\Catalog\Search\Contracts\SearchGateway;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\ParallelTesting;
use ReflectionProperty;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeSearchGateway;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingDatabase();
        $this->isolateSharedTestState();
        $this->app->instance(SearchGateway::class, new FakeSearchGateway);
    }

    /**
     * CI keeps Redis across tests while RefreshDatabase rolls MySQL back.
     * Permission lookups, IP rate limits, unique job locks, and catalog cache
     * must not see the previous test or a sibling Pest worker.
     *
     * Parallel workers must not Cache::flush() Redis (FLUSHDB) or share Meilisearch prefixes.
     */
    protected function isolateSharedTestState(): void
    {
        config(['permission.cache.store' => 'array']);

        $registrar = $this->app->make(PermissionRegistrar::class);
        $registrar->initializeCache();
        $registrar->forgetCachedPermissions();

        $token = ParallelTesting::token();
        if ($token !== false && $token !== '') {
            static $originalSearchPrefix;
            $originalSearchPrefix ??= (string) config('search.index_prefix');
            config([
                'search.index_prefix' => $originalSearchPrefix.'_p'.$token,
            ]);
        }

        $this->isolateCacheAndRateLimits(is_string($token) && $token !== '' ? $token : null);
    }

    /**
     * Empty the current test's cache without FLUSHDB.
     *
     * Use this instead of Cache::flush() in tests that share Redis with parallel workers.
     */
    protected function flushApplicationCacheSafely(): void
    {
        $token = ParallelTesting::token();

        $this->isolateCacheAndRateLimits(is_string($token) && $token !== '' ? $token : null);
    }

    /**
     * @param  non-empty-string|null  $workerToken
     */
    protected function isolateCacheAndRateLimits(?string $workerToken): void
    {
        $isolation = bin2hex(random_bytes(8));
        config(['testing.rate_limit_isolation' => $isolation]);

        $driver = (string) config('cache.default');

        if (! in_array($driver, ['redis', 'memcached', 'dynamodb'], true)) {
            Cache::flush();

            return;
        }

        static $originalPrefix;

        $originalPrefix ??= (string) config('cache.prefix');

        $worker = $workerToken ?? '0';
        config(['cache.prefix' => $originalPrefix.':pest:'.$worker.':'.$isolation]);

        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        Cache::clearResolvedInstance('cache');
        Cache::clearResolvedInstance('cache.store');

        $limiter = $this->app->make(CacheRateLimiter::class);
        $cacheProperty = new ReflectionProperty(CacheRateLimiter::class, 'cache');
        $cacheProperty->setValue($limiter, $this->app->make('cache')->driver());
    }

    /**
     * Prevent accidental connections to non-test databases.
     */
    protected function assertSafeTestingDatabase(): void
    {
        $connection = (string) config('database.default');

        if ($connection === 'sqlite') {
            $database = (string) config('database.connections.sqlite.database');

            if ($database !== ':memory:' && ! str_contains($database, 'test')) {
                throw new RuntimeException(
                    "Refusing to run tests against sqlite database [{$database}]. Use :memory: or a *test* database name.",
                );
            }

            return;
        }

        if ($connection === 'mysql') {
            $database = (string) config('database.connections.mysql.database');

            if ($database === '' || ! str_contains(strtolower($database), 'test')) {
                throw new RuntimeException(
                    "Refusing to run tests against MySQL database [{$database}]. Database name must contain 'test'.",
                );
            }
        }
    }
}
