<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Geography\Enums\SpatialAssignmentStatus;
use App\Domains\Geography\Enums\SpatialAssignmentType;
use App\Domains\Geography\Enums\SpatialGeometryVersionStatus;
use App\Domains\Geography\Enums\SpatialImportStatus;
use App\Domains\Geography\Enums\SpatialReviewStatus;
use App\Domains\Geography\Enums\SpatialValidationStatus;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Geography\Models\SpatialDataset;
use App\Domains\Geography\Models\SpatialDatasetVersion;
use App\Domains\Geography\Models\SpatialSource;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Geography\Models\SpatialZoneTranslation;
use App\Domains\Geography\Support\MultipolygonGeometry;
use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\LegalActivityType;
use App\Domains\Legal\Enums\LegalRuleEffect;
use App\Domains\Legal\Models\LegalProvision;
use App\Domains\Legal\Models\LegalRule;
use Database\Factories\SpatialZoneGeometryVersionFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class SpatialFixtures
{
    public static function fakeDisk(): void
    {
        Storage::fake('spatial_private');
    }

    public static function squareGeoJson(float $west = 44.0, float $south = 41.0, float $size = 1.0, string $id = 'TEST-ZONE-1'): string
    {
        $east = $west + $size;
        $north = $south + $size;

        return json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'id' => $id,
                'properties' => [
                    'code' => $id,
                    'title' => 'FICTIONAL Testus Reserve',
                ],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [$west, $south],
                        [$east, $south],
                        [$east, $north],
                        [$west, $north],
                        [$west, $south],
                    ]],
                ],
            ]],
        ], JSON_THROW_ON_ERROR);
    }

    public static function geoJsonUpload(string $contents, string $name = 'fictional.geojson'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    /**
     * @return array{source: SpatialSource, dataset: SpatialDataset, version: SpatialDatasetVersion, zone: SpatialZone, geometry: SpatialZoneGeometryVersion}
     */
    public static function publishedZone(
        User $actor,
        ?MultipolygonGeometry $geometry = null,
        string $jurisdiction = 'XX',
    ): array {
        self::fakeDisk();
        $geometry ??= SpatialZoneGeometryVersionFactory::square(44.0, 41.0, 1.0);
        $source = SpatialSource::factory()->verified()->create([
            'verified_by' => $actor->id,
            'jurisdiction_code' => $jurisdiction,
            'is_fictional' => true,
        ]);
        $dataset = SpatialDataset::factory()->create([
            'spatial_source_id' => $source->id,
            'jurisdiction_code' => $jurisdiction,
            'is_fictional' => true,
            'created_by' => $actor->id,
        ]);
        $path = 'test/'.$dataset->public_id.'.geojson';
        Storage::disk('spatial_private')->put($path, self::squareGeoJson());
        $version = SpatialDatasetVersion::factory()->published()->create([
            'spatial_dataset_id' => $dataset->id,
            'storage_path' => $path,
            'retrieved_by' => $actor->id,
            'published_by' => $actor->id,
            'import_status' => SpatialImportStatus::Imported,
            'review_status' => SpatialReviewStatus::Published,
        ]);
        $dataset->current_version_id = $version->id;
        $dataset->save();
        $zone = SpatialZone::factory()->active()->create([
            'spatial_dataset_id' => $dataset->id,
            'jurisdiction_code' => $jurisdiction,
            'is_fictional' => true,
        ]);
        SpatialZoneTranslation::query()->create([
            'spatial_zone_id' => $zone->id,
            'locale' => 'en',
            'name' => $zone->default_name,
        ]);
        SpatialZoneTranslation::query()->create([
            'spatial_zone_id' => $zone->id,
            'locale' => 'ka',
            'name' => $zone->default_name,
        ]);
        $bbox = $geometry->boundingBox();
        $centroid = $geometry->centroid();
        $row = SpatialZoneGeometryVersion::recordCanonical([
            'spatial_zone_id' => $zone->id,
            'spatial_dataset_version_id' => $version->id,
            'geometry_wkt' => $geometry->toWkt(),
            'min_longitude' => $bbox->west->value,
            'min_latitude' => $bbox->south->value,
            'max_longitude' => $bbox->east->value,
            'max_latitude' => $bbox->north->value,
            'centroid_longitude' => $centroid->longitude->value,
            'centroid_latitude' => $centroid->latitude->value,
            'vertex_count' => $geometry->vertexCount(),
            'polygon_count' => $geometry->polygonCount(),
            'geometry_checksum' => $geometry->checksum(),
            'source_feature_identifier' => $zone->external_identifier,
            'effective_from' => now()->subYear(),
            'status' => SpatialGeometryVersionStatus::Published->value,
            'validation_status' => SpatialValidationStatus::Valid->value,
            'published_at' => now(),
            'published_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $geometry);

        return compact('source', 'dataset', 'version', 'zone', 'geometry') + ['geometry' => $row];
    }

    public static function assignProhibition(SpatialZone $zone, LegalRule $rule, User $actor, SpatialAssignmentType $type = SpatialAssignmentType::ProhibitedWithin): LegalRuleSpatialZone
    {
        return LegalRuleSpatialZone::factory()->published()->create([
            'spatial_zone_id' => $zone->id,
            'legal_rule_id' => $rule->id,
            'assignment_type' => $type,
            'status' => SpatialAssignmentStatus::Published,
            'created_by' => $actor->id,
            'reviewed_by' => $actor->id,
        ]);
    }

    public static function publishedHuntingRule(LegalProvision $provision, User $actor, LegalRuleEffect $effect = LegalRuleEffect::Prohibit): LegalRule
    {
        return LegalFixtures::publishedRule($provision, $actor, $effect, LegalActivityType::Hunting, null, 'FICTIONAL spatial rule');
    }
}
