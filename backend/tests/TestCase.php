<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingDatabase();
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
