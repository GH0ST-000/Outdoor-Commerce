<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Support\BoundingBox;
use App\Domains\Geography\Support\MultipolygonGeometry;
use App\Domains\Geography\Support\SpatialLogger;
use JsonException;

final class GeoJsonValidator
{
    public function __construct(private readonly SpatialLogger $logger) {}

    /**
     * @return array{
     *     features: list<array{id: string, properties: array<string, mixed>, geometry: array<string, mixed>, geometry_object: MultipolygonGeometry}>,
     *     property_keys: list<string>,
     *     bounds: array{west: float, south: float, east: float, north: float, srid: int},
     *     feature_count: int,
     *     vertex_count: int,
     *     geometry_types: array<string, int>,
     *     warnings: list<string>
     * }
     */
    public function parse(string $json, string $confirmedCrs): array
    {
        $this->assertCrs($confirmedCrs);
        $maxBytes = (int) config('spatial.import.max_json_bytes', 20 * 1024 * 1024);
        if (strlen($json) > $maxBytes) {
            throw SpatialException::fileRejected('The GeoJSON payload exceeds the size limit.');
        }

        try {
            $decoded = json_decode($json, true, (int) config('spatial.import.max_nesting_depth', 32), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw SpatialException::invalidGeometry('The file is not valid JSON.', ['reason' => $exception->getMessage()]);
        }

        if (! is_array($decoded)) {
            throw SpatialException::invalidGeometry('The GeoJSON root must be an object.');
        }
        if (isset($decoded['crs'])) {
            $this->assertEmbeddedCrs($decoded['crs']);
        }

        $type = (string) ($decoded['type'] ?? '');
        $rawFeatures = match ($type) {
            'FeatureCollection' => $decoded['features'] ?? null,
            'Feature' => [$decoded],
            'Polygon', 'MultiPolygon' => [[
                'type' => 'Feature',
                'id' => 'feature-0',
                'properties' => [],
                'geometry' => $decoded,
            ]],
            default => throw SpatialException::invalidGeometry('Unsupported GeoJSON type.', ['type' => $type]),
        };

        if (! is_array($rawFeatures)) {
            throw SpatialException::invalidGeometry('FeatureCollection.features must be an array.');
        }
        if ($rawFeatures === []) {
            throw SpatialException::invalidGeometry('The FeatureCollection is empty.');
        }

        $maxFeatures = (int) config('spatial.import.max_features', 2000);
        if (count($rawFeatures) > $maxFeatures) {
            throw SpatialException::fileRejected('The dataset exceeds the maximum feature count.');
        }

        $features = [];
        $keys = [];
        $types = [];
        $vertexTotal = 0;
        $seenIds = [];
        $warnings = [];
        $west = 180.0;
        $east = -180.0;
        $south = 90.0;
        $north = -90.0;

        foreach ($rawFeatures as $index => $raw) {
            if (! is_array($raw)) {
                throw SpatialException::invalidGeometry('Each feature must be an object.', ['index' => $index]);
            }
            $geometry = $raw['geometry'] ?? null;
            if (! is_array($geometry)) {
                throw SpatialException::invalidGeometry('Each feature needs a geometry object.', ['index' => $index]);
            }
            $geometryType = (string) ($geometry['type'] ?? '');
            $types[$geometryType] = ($types[$geometryType] ?? 0) + 1;
            $parsed = MultipolygonGeometry::fromGeoJson($geometry);
            $vertexTotal += $parsed->vertexCount();
            $maxVertices = (int) config('spatial.import.max_vertices', 200000);
            if ($vertexTotal > $maxVertices) {
                throw SpatialException::fileRejected('The dataset exceeds the maximum vertex count.');
            }
            $perFeature = (int) config('spatial.import.max_vertices_per_feature', 50000);
            if ($parsed->vertexCount() > $perFeature) {
                throw SpatialException::invalidGeometry('A feature exceeds the per-feature vertex limit.', ['index' => $index]);
            }

            $properties = is_array($raw['properties'] ?? null) ? $raw['properties'] : [];
            foreach (array_keys($properties) as $key) {
                $keys[(string) $key] = true;
            }

            $id = (string) ($raw['id'] ?? $properties['id'] ?? $properties['identifier'] ?? 'feature-'.$index);
            if (isset($seenIds[$id])) {
                throw SpatialException::invalidGeometry('Duplicate feature identifiers are not allowed.', ['identifier' => $id]);
            }
            $seenIds[$id] = true;

            $bbox = $parsed->boundingBox();
            $west = min($west, $bbox->west->value);
            $east = max($east, $bbox->east->value);
            $south = min($south, $bbox->south->value);
            $north = max($north, $bbox->north->value);

            $features[] = [
                'id' => $id,
                'properties' => $properties,
                'geometry' => $parsed->toGeoJson(),
                'geometry_object' => $parsed,
            ];
        }

        if (isset($types['Point']) || isset($types['LineString']) || isset($types['GeometryCollection'])) {
            $warnings[] = 'Unsupported geometry types were rejected during parse.';
        }

        $this->logger->info('geojson_validated', [
            'feature_count' => count($features),
            'vertex_count' => $vertexTotal,
        ]);

        return [
            'features' => $features,
            'property_keys' => array_keys($keys),
            'bounds' => BoundingBox::fromEdges($west, $south, $east, $north)->toArray(),
            'feature_count' => count($features),
            'vertex_count' => $vertexTotal,
            'geometry_types' => $types,
            'warnings' => $warnings,
        ];
    }

    public function assertCrs(string $crs): void
    {
        $normalized = strtoupper(trim($crs));
        $allowed = array_map('strtoupper', config('spatial.import.accepted_crs', ['4326', 'EPSG:4326', 'CRS84']));
        if (! in_array($normalized, $allowed, true)) {
            throw SpatialException::unsupportedCrs($crs);
        }
    }

    private function assertEmbeddedCrs(mixed $crs): void
    {
        if (! is_array($crs)) {
            throw SpatialException::unsupportedCrs('embedded');
        }
        $name = (string) data_get($crs, 'properties.name', data_get($crs, 'type', ''));
        $this->assertCrs($name);
    }
}
