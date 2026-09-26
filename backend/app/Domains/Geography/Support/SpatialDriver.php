<?php

declare(strict_types=1);

namespace App\Domains\Geography\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SpatialDriver
{
    public static function supportsNativeGeometry(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    public static function persistNativeGeometry(int $geometryVersionId, string $wkt): void
    {
        if (! self::supportsNativeGeometry()) {
            return;
        }

        DB::update(
            'UPDATE spatial_zone_geometry_versions SET geometry = ST_GeomFromText(?, 4326) WHERE id = ?',
            [$wkt, $geometryVersionId],
        );
    }
}
