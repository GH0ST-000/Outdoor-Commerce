<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingDatabase();
        $this->isolateSharedTestState();
    }

    /**
     * CI keeps Redis across tests while RefreshDatabase rolls MySQL back.
     * Permission lookups and IP rate limits must not see the previous test.
     */
    protected function isolateSharedTestState(): void
    {
        config(['permission.cache.store' => 'array']);

        $registrar = $this->app->make(PermissionRegistrar::class);
        $registrar->initializeCache();
        $registrar->forgetCachedPermissions();

        foreach (['127.0.0.1', '::1'] as $ip) {
            RateLimiter::clear(md5('auth.register'.$ip));
            RateLimiter::clear(md5('catalog.public'.$ip));
            RateLimiter::clear(md5('catalog.public.list'.$ip));
            RateLimiter::clear(md5('catalog.public.list'.$ip.'|list'));
            RateLimiter::clear(md5('catalog.public.list'.$ip.'|search'));
            RateLimiter::clear(md5('catalog.public.facets'.$ip));
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
