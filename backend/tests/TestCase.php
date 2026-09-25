<?php

declare(strict_types=1);

namespace Tests;

use App\Domains\Catalog\Search\Contracts\SearchGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\RateLimiter;
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
     * Permission lookups and IP rate limits must not see the previous test.
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
            // Laravel already prefixes cache per worker. Cache::flush() on Redis
            // uses FLUSHDB and would wipe sibling processes.
            config([
                'search.index_prefix' => (string) config('search.index_prefix').'_p'.$token,
            ]);
        } else {
            Cache::flush();
        }

        foreach (['127.0.0.1', '::1'] as $ip) {
            RateLimiter::clear(md5('auth.register'.$ip));
            RateLimiter::clear(md5('catalog.public'.$ip));
            RateLimiter::clear(md5('catalog.public.list'.$ip));
            RateLimiter::clear(md5('catalog.public.list'.$ip.'|list'));
            RateLimiter::clear(md5('catalog.public.list'.$ip.'|search'));
            RateLimiter::clear(md5('catalog.public.facets'.$ip));
            RateLimiter::clear(md5('search.public'.$ip));
            RateLimiter::clear(md5('search.suggest'.$ip));
            RateLimiter::clear('g'.$ip);
            RateLimiter::clear(md5('cart.read'.$ip));
            RateLimiter::clear(md5('cart.mutate'.$ip));
            RateLimiter::clear(md5('checkout.read'.$ip));
            RateLimiter::clear(md5('checkout.mutate'.$ip));
            RateLimiter::clear(md5('checkout.quote'.$ip));
        }
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
