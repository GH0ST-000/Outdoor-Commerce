<?php

declare(strict_types=1);

namespace App\Domains\Geography\Queries;

use App\Domains\Geography\DTOs\PointClassificationData;
use App\Domains\Geography\DTOs\PointLookupQueryData;
use App\Domains\Geography\Enums\SpatialRelation;
use App\Domains\Geography\Enums\SpatialZoneStatus;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Geography\Support\BoundingBox;
use App\Domains\Geography\Support\GeoCoordinate;
use App\Domains\Geography\Support\SpatialDriver;
use App\Domains\Geography\Support\SpatialLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class FindZonesContainingPointQuery
{
    public function __construct(private readonly SpatialLogger $logger) {}

    /**
     * @return list<array{geometry: SpatialZoneGeometryVersion, classification: PointClassificationData}>
     */
    public function execute(PointLookupQueryData $query): array
    {
        $at = Carbon::instance(\DateTimeImmutable::createFromInterface($query->at));
        $point = GeoCoordinate::fromLngLat($query->longitude, $query->latitude);
        $warning = (float) config('spatial.query.boundary_warning_degrees', 0.001);
        $pad = max($warning, 0.0001);
        $candidates = $this->candidates($point, $at, $query->jurisdictionCode, $query->zoneTypes, $pad);
        $matches = [];
        $predicateCount = 0;

        foreach ($candidates as $row) {
            $predicateCount++;
            $classification = $row->geometry()->classify($point, $warning);
            if (
                $classification->relation === SpatialRelation::Outside
                && ! $classification->nearBoundary
            ) {
                continue;
            }
            $matches[] = [
                'geometry' => $row,
                'classification' => $classification,
            ];
        }

        $this->logger->info('point_lookup', [
            'candidate_count' => $candidates->count(),
            'exact_predicate_count' => $predicateCount,
            'match_count' => count($matches),
        ]);

        return $matches;
    }

    /**
     * @param  list<string>|null  $zoneTypes
     * @return Collection<int, SpatialZoneGeometryVersion>
     */
    private function candidates(
        GeoCoordinate $point,
        Carbon $at,
        string $jurisdiction,
        ?array $zoneTypes,
        float $pad,
    ): Collection {
        $bbox = BoundingBox::fromEdges(
            max(-180.0, $point->longitude->value - $pad),
            max(-90.0, $point->latitude->value - $pad),
            min(180.0, $point->longitude->value + $pad),
            min(90.0, $point->latitude->value + $pad),
        );

        $query = SpatialZoneGeometryVersion::query()
            ->with(['zone.translations', 'zone.dataset.source'])
            ->publishedEffective($at)
            ->intersectingBbox($bbox)
            ->whereHas('zone', function ($builder) use ($jurisdiction, $zoneTypes): void {
                $builder->where('status', SpatialZoneStatus::Active)
                    ->where('jurisdiction_code', $jurisdiction);
                if ($zoneTypes !== null && $zoneTypes !== []) {
                    $builder->whereIn('zone_type', $zoneTypes);
                }
            });

        if (SpatialDriver::supportsNativeGeometry() && (bool) config('spatial.query.mysql_mbr_candidate_filter', true)) {
            // A raw point misses zones whose box starts just outside it. The pad is the
            // same distance later used to flag a boundary warning.
            $query->whereRaw(
                'MBRIntersects(geometry, ST_GeomFromText(?, 4326))',
                [$this->envelopeWkt($bbox)],
            );
        }

        return $query->get();
    }

    private function envelopeWkt(BoundingBox $bbox): string
    {
        $west = $bbox->west->value;
        $south = $bbox->south->value;
        $east = $bbox->east->value;
        $north = $bbox->north->value;

        return sprintf(
            'POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
            $west,
            $south,
            $east,
            $south,
            $east,
            $north,
            $west,
            $north,
            $west,
            $south,
        );
    }
}
