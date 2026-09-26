<?php

declare(strict_types=1);

namespace App\Domains\Geography\Support;

use App\Domains\Geography\DTOs\PointClassificationData;
use App\Domains\Geography\Enums\SpatialRelation;
use App\Domains\Geography\Exceptions\SpatialException;

/**
 * Canonical legal geometry: MULTIPOLYGON in SRID 4326, positions longitude then latitude.
 *
 * Boundary policy (application-defined, not a MySQL edge-case):
 * - A point within {@see $onBoundaryEpsilon} degrees of a ring is on_boundary.
 * - Ray-casting decides interior, excluding holes.
 * - Distance to the nearest ring uses planar degrees (not metres).
 */
final readonly class MultipolygonGeometry
{
    public const SRID = 4326;

    /**
     * @param  list<list<list<array{0: float, 1: float}>>>  $polygons
     */
    private function __construct(
        private array $polygons,
        private float $onBoundaryEpsilon = 1.0e-9,
    ) {}

    /**
     * @param  array<string, mixed>  $geometry
     */
    public static function fromGeoJson(array $geometry): self
    {
        $type = (string) ($geometry['type'] ?? '');
        $coordinates = $geometry['coordinates'] ?? null;
        if (! is_array($coordinates)) {
            throw SpatialException::invalidGeometry('GeoJSON coordinates are required.');
        }

        return match ($type) {
            'Polygon' => new self([self::normalizePolygon($coordinates)]),
            'MultiPolygon' => new self(self::normalizeMultiPolygon($coordinates)),
            default => throw SpatialException::invalidGeometry(
                'Published zones require Polygon or MultiPolygon geometry.',
                ['type' => $type],
            ),
        };
    }

    public static function fromWkt(string $wkt): self
    {
        $trimmed = strtoupper(trim($wkt));
        if (! str_starts_with($trimmed, 'MULTIPOLYGON') && ! str_starts_with($trimmed, 'POLYGON')) {
            throw SpatialException::invalidGeometry('WKT must be POLYGON or MULTIPOLYGON.');
        }

        if (str_starts_with($trimmed, 'POLYGON')) {
            $wkt = 'MULTIPOLYGON('.substr(trim($wkt), strlen('POLYGON')).')';
        }

        if (! preg_match('/MULTIPOLYGON\s*\((.*)\)\s*$/is', $wkt, $match)) {
            throw SpatialException::invalidGeometry('Unable to parse MULTIPOLYGON WKT.');
        }

        $polygons = [];
        $body = $match[1];
        if (! preg_match_all('/\((\((?:[^()]+|\([^()]*\))*\))\)/s', '('.$body.')', $groups)) {
            $groups = [1 => []];
        }

        foreach (self::splitTopLevelPolygons($body) as $polygonBody) {
            $rings = [];
            foreach (self::splitRings($polygonBody) as $ringBody) {
                $rings[] = self::parseWktRing($ringBody);
            }
            $polygons[] = self::normalizePolygon($rings);
        }

        return new self($polygons);
    }

    public function toWkt(): string
    {
        $polygons = [];
        foreach ($this->polygons as $polygon) {
            $rings = [];
            foreach ($polygon as $ring) {
                $pairs = [];
                foreach ($ring as $position) {
                    $pairs[] = $this->formatNumber($position[0]).' '.$this->formatNumber($position[1]);
                }
                $rings[] = '('.implode(', ', $pairs).')';
            }
            $polygons[] = '('.implode(', ', $rings).')';
        }

        return 'MULTIPOLYGON('.implode(', ', $polygons).')';
    }

    /**
     * @return array{type: string, coordinates: list<mixed>}
     */
    public function toGeoJson(): array
    {
        return [
            'type' => 'MultiPolygon',
            'coordinates' => $this->polygons,
        ];
    }

    public function checksum(): string
    {
        return hash('sha256', $this->toWkt());
    }

    public function vertexCount(): int
    {
        $count = 0;
        foreach ($this->polygons as $polygon) {
            foreach ($polygon as $ring) {
                $count += max(count($ring) - 1, 0);
            }
        }

        return $count;
    }

    public function polygonCount(): int
    {
        return count($this->polygons);
    }

    public function boundingBox(): BoundingBox
    {
        $west = 180.0;
        $east = -180.0;
        $south = 90.0;
        $north = -90.0;
        foreach ($this->polygons as $polygon) {
            foreach ($polygon[0] as $position) {
                $west = min($west, $position[0]);
                $east = max($east, $position[0]);
                $south = min($south, $position[1]);
                $north = max($north, $position[1]);
            }
        }

        return BoundingBox::fromEdges($west, $south, $east, $north);
    }

    public function centroid(): GeoCoordinate
    {
        $sumLng = 0.0;
        $sumLat = 0.0;
        $count = 0;
        foreach ($this->polygons as $polygon) {
            $ring = $polygon[0];
            $limit = count($ring) - 1;
            for ($i = 0; $i < $limit; $i++) {
                $sumLng += $ring[$i][0];
                $sumLat += $ring[$i][1];
                $count++;
            }
        }

        if ($count === 0) {
            throw SpatialException::invalidGeometry('Cannot compute centroid for empty geometry.');
        }

        return GeoCoordinate::fromLngLat($sumLng / $count, $sumLat / $count);
    }

    public function classify(GeoCoordinate $point, float $warningDegrees): PointClassificationData
    {
        $distance = $this->minimumBoundaryDistanceDegrees($point);
        $onBoundary = $distance <= $this->onBoundaryEpsilon;
        $inside = $this->contains($point);

        $relation = SpatialRelation::Outside;
        if ($onBoundary) {
            $relation = SpatialRelation::OnBoundary;
        } elseif ($inside) {
            $relation = SpatialRelation::Inside;
        }

        return new PointClassificationData(
            relation: $relation,
            nearBoundary: $distance <= $warningDegrees,
            boundaryDistanceDegrees: $distance,
            point: $point,
            warningDegrees: $warningDegrees,
        );
    }

    public function contains(GeoCoordinate $point): bool
    {
        foreach ($this->polygons as $polygon) {
            if (! $this->pointInRing($point, $polygon[0])) {
                continue;
            }
            $inHole = false;
            for ($i = 1, $len = count($polygon); $i < $len; $i++) {
                if ($this->pointInRing($point, $polygon[$i])) {
                    $inHole = true;
                    break;
                }
            }
            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    public function minimumBoundaryDistanceDegrees(GeoCoordinate $point): float
    {
        $min = INF;
        foreach ($this->polygons as $polygon) {
            foreach ($polygon as $ring) {
                $min = min($min, $this->distanceToRing($point, $ring));
            }
        }

        return is_finite($min) ? $min : INF;
    }

    /**
     * @param  list<mixed>  $coordinates
     * @return list<list<array{0: float, 1: float}>>
     */
    private static function normalizePolygon(array $coordinates): array
    {
        if ($coordinates === []) {
            throw SpatialException::invalidGeometry('Polygon has no rings.');
        }
        $rings = [];
        foreach ($coordinates as $index => $ring) {
            if (! is_array($ring)) {
                throw SpatialException::invalidGeometry('Polygon ring is invalid.', ['ring' => $index]);
            }
            $rings[] = self::normalizeRing($ring, $index === 0);
        }

        return $rings;
    }

    /**
     * @param  list<mixed>  $coordinates
     * @return list<list<list<array{0: float, 1: float}>>>
     */
    private static function normalizeMultiPolygon(array $coordinates): array
    {
        if ($coordinates === []) {
            throw SpatialException::invalidGeometry('MultiPolygon is empty.');
        }
        $polygons = [];
        foreach ($coordinates as $polygon) {
            if (! is_array($polygon)) {
                throw SpatialException::invalidGeometry('MultiPolygon member is invalid.');
            }
            $polygons[] = self::normalizePolygon($polygon);
        }

        return $polygons;
    }

    /**
     * @param  list<mixed>  $ring
     * @return list<array{0: float, 1: float}>
     */
    private static function normalizeRing(array $ring, bool $exterior): array
    {
        $positions = [];
        foreach ($ring as $position) {
            if (! is_array($position) || ! isset($position[0], $position[1])) {
                throw SpatialException::invalidGeometry('Each position must be [longitude, latitude].');
            }
            $lng = (float) $position[0];
            $lat = (float) $position[1];
            GeoCoordinate::fromLngLat($lng, $lat);
            $positions[] = [$lng, $lat];
        }

        if (count($positions) < 4) {
            throw SpatialException::invalidGeometry('A linear ring needs at least four positions.');
        }

        $first = $positions[0];
        $last = $positions[array_key_last($positions)];
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            throw SpatialException::invalidGeometry('Linear rings must be closed.');
        }

        $distinct = [];
        foreach (array_slice($positions, 0, -1) as $vertex) {
            $distinct[$vertex[0].','.$vertex[1]] = true;
        }
        if (count($distinct) < 3) {
            throw SpatialException::invalidGeometry('A ring needs at least three distinct vertices.');
        }

        unset($exterior);

        return $positions;
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private static function parseWktRing(string $body): array
    {
        $positions = [];
        foreach (preg_split('/\s*,\s*/', trim($body)) ?: [] as $pair) {
            $parts = preg_split('/\s+/', trim($pair)) ?: [];
            if (count($parts) < 2) {
                throw SpatialException::invalidGeometry('Invalid WKT vertex.');
            }
            $positions[] = [(float) $parts[0], (float) $parts[1]];
        }

        return $positions;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelPolygons(string $body): array
    {
        $polygons = [];
        $depth = 0;
        $start = null;
        $length = strlen($body);
        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];
            if ($char === '(') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $polygons[] = substr($body, $start + 1, $i - $start - 1);
                    $start = null;
                }
            }
        }

        if ($polygons === []) {
            throw SpatialException::invalidGeometry('MULTIPOLYGON contains no polygons.');
        }

        return $polygons;
    }

    /**
     * @return list<string>
     */
    private static function splitRings(string $polygonBody): array
    {
        $rings = [];
        $depth = 0;
        $start = null;
        $length = strlen($polygonBody);
        for ($i = 0; $i < $length; $i++) {
            $char = $polygonBody[$i];
            if ($char === '(') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $rings[] = substr($polygonBody, $start + 1, $i - $start - 1);
                    $start = null;
                }
            }
        }

        if ($rings === []) {
            throw SpatialException::invalidGeometry('Polygon contains no rings.');
        }

        return $rings;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     */
    private function pointInRing(GeoCoordinate $point, array $ring): bool
    {
        $x = $point->longitude->value;
        $y = $point->latitude->value;
        $inside = false;
        $count = count($ring);
        $j = $count - 1;
        for ($i = 0; $i < $count; $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];
            $intersects = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1.0e-15) + $xi);
            if ($intersects) {
                $inside = ! $inside;
            }
            $j = $i;
        }

        return $inside;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     */
    private function distanceToRing(GeoCoordinate $point, array $ring): float
    {
        $min = INF;
        $count = count($ring);
        for ($i = 0; $i < $count - 1; $i++) {
            $min = min($min, $this->distanceToSegment(
                $point->longitude->value,
                $point->latitude->value,
                $ring[$i][0],
                $ring[$i][1],
                $ring[$i + 1][0],
                $ring[$i + 1][1],
            ));
        }

        return $min;
    }

    private function distanceToSegment(
        float $px,
        float $py,
        float $ax,
        float $ay,
        float $bx,
        float $by,
    ): float {
        $dx = $bx - $ax;
        $dy = $by - $ay;
        if ($dx === 0.0 && $dy === 0.0) {
            return hypot($px - $ax, $py - $ay);
        }
        $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / ($dx * $dx + $dy * $dy);
        $t = max(0.0, min(1.0, $t));

        return hypot($px - ($ax + $t * $dx), $py - ($ay + $t * $dy));
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
