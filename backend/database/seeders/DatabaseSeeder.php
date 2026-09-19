<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Administrators are never seeded for production. Use:
     * `php artisan access-control:sync` then `php artisan admin:create`.
     *
     * Local catalog demo data: `php artisan db:seed --class=CatalogDemoSeeder`
     */
    public function run(): void
    {
        if ($this->command?->option('class') === null && app()->environment('local')) {
            // Optional local demo — not run automatically to keep migrate:fresh clean.
        }
    }
}
