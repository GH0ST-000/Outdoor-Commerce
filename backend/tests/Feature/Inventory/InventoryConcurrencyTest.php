<?php

declare(strict_types=1);

it('prevents overselling under mysql concurrency', function (): void {
    if (config('database.default') !== 'mysql') {
        $this->markTestSkipped('MySQL is required to validate row-lock concurrency behavior.');
    }

    // Placeholder for dedicated MySQL integration suite.
})->skip('Enable when CI provides MySQL for inventory concurrency tests.');
