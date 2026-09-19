<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;

it('can ping redis when redis cache store is configured', function (): void {
    if (config('cache.default') !== 'redis') {
        $this->markTestSkipped('Redis cache store is not configured for this run.');
    }

    expect(Redis::connection()->ping())->toBeTruthy();
});
