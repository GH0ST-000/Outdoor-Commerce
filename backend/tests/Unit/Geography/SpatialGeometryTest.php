<?php

declare(strict_types=1);

use App\Domains\Geography\Enums\SpatialRelation;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Services\GeoJsonValidator;
use App\Domains\Geography\Support\BoundingBox;
use App\Domains\Geography\Support\GeoCoordinate;
use App\Domains\Geography\Support\Latitude;
use App\Domains\Geography\Support\Longitude;
use App\Domains\Geography\Support\MultipolygonGeometry;
use App\Domains\Geography\Support\SpatialLogger;

function square(float $west, float $south, float $size = 1.0): MultipolygonGeometry
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

it('converts a valid polygon to a multipolygon and preserves checksum stability', function (): void {
    $geometry = square(44, 41);
    expect($geometry->toGeoJson()['type'])->toBe('MultiPolygon')
        ->and($geometry->checksum())->toBe($geometry->checksum())
        ->and($geometry->vertexCount())->toBe(4)
        ->and($geometry->polygonCount())->toBe(1);
    $fromWkt = MultipolygonGeometry::fromWkt($geometry->toWkt());
    expect($fromWkt->checksum())->toBe($geometry->checksum());
});

it('rejects invalid coordinates, empty geometry, unclosed rings, and unsupported types', function (): void {
    expect(fn () => new Longitude(181))->toThrow(SpatialException::class);
    expect(fn () => new Latitude(-91))->toThrow(SpatialException::class);
    expect(fn () => MultipolygonGeometry::fromGeoJson(['type' => 'Point', 'coordinates' => [0, 0]]))
        ->toThrow(SpatialException::class);
    expect(fn () => MultipolygonGeometry::fromGeoJson(['type' => 'Polygon', 'coordinates' => []]))
        ->toThrow(SpatialException::class);
    expect(fn () => MultipolygonGeometry::fromGeoJson([
        'type' => 'Polygon',
        'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1]]],
    ]))->toThrow(SpatialException::class);
});

it('classifies inside, outside, on-boundary, near-boundary, holes, and disconnected parts', function (): void {
    $geometry = square(44, 41, 1);
    $inside = $geometry->classify(GeoCoordinate::fromLngLat(44.5, 41.5), 0.05);
    $outside = $geometry->classify(GeoCoordinate::fromLngLat(50, 50), 0.05);
    $edge = $geometry->classify(GeoCoordinate::fromLngLat(44.0, 41.5), 0.05);
    $near = $geometry->classify(GeoCoordinate::fromLngLat(43.9995, 41.5), 0.001);

    expect($inside->relation)->toBe(SpatialRelation::Inside)
        ->and($outside->relation)->toBe(SpatialRelation::Outside)
        ->and($edge->relation)->toBe(SpatialRelation::OnBoundary)
        ->and($near->nearBoundary)->toBeTrue();

    $donut = MultipolygonGeometry::fromGeoJson([
        'type' => 'Polygon',
        'coordinates' => [
            [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]],
            [[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]],
        ],
    ]);
    expect($donut->contains(GeoCoordinate::fromLngLat(2, 2)))->toBeFalse()
        ->and($donut->contains(GeoCoordinate::fromLngLat(0.5, 0.5)))->toBeTrue();

    $multi = MultipolygonGeometry::fromGeoJson([
        'type' => 'MultiPolygon',
        'coordinates' => [
            [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]],
            [[[5, 5], [6, 5], [6, 6], [5, 6], [5, 5]]],
        ],
    ]);
    expect($multi->contains(GeoCoordinate::fromLngLat(0.5, 0.5)))->toBeTrue()
        ->and($multi->contains(GeoCoordinate::fromLngLat(5.5, 5.5)))->toBeTrue()
        ->and($multi->contains(GeoCoordinate::fromLngLat(3, 3)))->toBeFalse();
});

it('computes bounding boxes with longitude-before-latitude ordering', function (): void {
    $box = BoundingBox::fromEdges(44, 41, 45, 42);
    expect($box->contains(GeoCoordinate::fromLngLat(44.5, 41.5)))->toBeTrue()
        ->and($box->toArray()['srid'])->toBe(4326)
        ->and(GeoCoordinate::fromLngLat(10, 20)->toGeoJsonPosition())->toBe([10.0, 20.0]);
});

it('validates GeoJSON collections and rejects empty, invalid JSON, and unsupported CRS', function (): void {
    $validator = new GeoJsonValidator(new SpatialLogger);
    $valid = $validator->parse('{"type":"FeatureCollection","features":[{"type":"Feature","id":"A","properties":{"code":"A"},"geometry":{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,1],[0,0]]]}}]}', 'EPSG:4326');
    expect($valid['feature_count'])->toBe(1);

    expect(fn () => $validator->parse('{', 'EPSG:4326'))->toThrow(SpatialException::class);
    expect(fn () => $validator->parse('{"type":"FeatureCollection","features":[]}', 'EPSG:4326'))->toThrow(SpatialException::class);
    expect(fn () => $validator->assertCrs('EPSG:32638'))->toThrow(SpatialException::class);
});
