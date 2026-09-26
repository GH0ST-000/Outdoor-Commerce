<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Legal\Services\OfficialGeorgianSnapshotImporter;
use Illuminate\Database\Seeder;

final class GeorgianHuntingDailyLimitSeeder extends Seeder
{
    public function run(): void
    {
        app(OfficialGeorgianSnapshotImporter::class)->import(section: 'limits');
    }
}
