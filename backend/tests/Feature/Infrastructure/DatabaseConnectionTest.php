<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->artisan('migrate', ['--force' => true]);
});

it('runs against the configured database connection', function (): void {
    expect(config('app.env'))->toBe('testing');

    expect(Schema::hasTable('migrations'))->toBeTrue();
    expect(DB::table('migrations')->count())->toBeGreaterThan(0);
});
