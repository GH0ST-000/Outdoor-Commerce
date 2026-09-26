<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Legal\Services\OfficialGeorgianSnapshotImporter;
use Illuminate\Database\Seeder;

/**
 * Recreational fishing restrictions are intentionally empty until the current
 * consolidated regulation is publicly retrieved.
 */
final class GeorgianFishingRestrictionSeeder extends Seeder
{
    public function run(): void
    {
        app(OfficialGeorgianSnapshotImporter::class)->import(section: 'fishing');
    }
}
