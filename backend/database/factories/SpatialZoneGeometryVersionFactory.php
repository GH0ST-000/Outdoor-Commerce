<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Enums\SpatialGeometryVersionStatus;
use App\Domains\Geography\Enums\SpatialValidationStatus;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Geography\Support\MultipolygonGeometry;
use App\Domains\Geography\Support\SpatialDriver;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test-only fictional geometry. Never use in production seeds.
 *
 * @extends Factory<SpatialZoneGeometryVersion>
 */
class SpatialZoneGeometryVersionFactory extends Factory
{
    protected $model = SpatialZoneGeometryVersion::class;

    public function definition(): array
    {
        $geometry = self::square(44.0, 41.0, 1.0);

        return [
            'public_id' => (string) Str::uuid(),
            'spatial_zone_id' => SpatialZone::factory(),
            'spatial_dataset_version_id' => SpatialDatasetVersion::factory(),
            'geometry_wkt' => $geometry->toWkt(),
            'min_longitude' => 44.0,
            'min_latitude' => 41.0,
            'max_longitude' => 45.0,
            'max_latitude' => 42.0,
            'centroid_longitude' => 44.5,
            'centroid_latitude' => 41.5,
            'vertex_count' => $geometry->vertexCount(),
            'polygon_count' => $geometry->polygonCount(),
            'geometry_checksum' => $geometry->checksum(),
            'source_feature_identifier' => 'TEST-'.$this->faker->unique()->numerify('####'),
            'effective_from' => now()->subYear(),
            'status' => SpatialGeometryVersionStatus::Draft,
            'validation_status' => SpatialValidationStatus::Valid,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SpatialZoneGeometryVersion $version): void {
            SpatialDriver::persistNativeGeometry($version->id, $version->geometry_wkt);
        });
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => SpatialGeometryVersionStatus::Published,
            'published_at' => now(),
        ]);
    }

    public static function square(float $west, float $south, float $size = 1.0): MultipolygonGeometry
    {
        $east = $west + $size;
        $north = $south + $size;

        return MultipolygonGeometry::fromGeoJson([
            'type' => 'Polygon',
            'coordinates' => [[
                [$west, $south],
                [$east, $south],
                [$east, $north],
                [$west, $north],
                [$west, $south],
            ]],
        ]);
    }
}
