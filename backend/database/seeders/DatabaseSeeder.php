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
     * Local storefront data: CatalogDemoSeeder drafts plus prices/media so
     * `/catalog/hunting` can load against the public catalog API.
     */
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            $this->call(PublicCatalogDemoSeeder::class);
        }
    }
}
