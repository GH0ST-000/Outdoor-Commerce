<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Legal\Services\OfficialGeorgianSnapshotImporter;
use Illuminate\Database\Seeder;

/**
 * Does not assign legal rules to zones. Geometry has not been downloaded,
 * and missing geometry is incomplete coverage rather than permission.
 */
final class GeorgianSpatialRuleAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        app(OfficialGeorgianSnapshotImporter::class)->import(section: 'assignments');
    }
}
