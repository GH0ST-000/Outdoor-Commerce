<?php

declare(strict_types=1);

namespace App\Domains\Geography\Services;

use App\Domains\Geography\DTOs\PointLookupQueryData;
use App\Domains\Geography\Enums\SpatialDetailLevel;
use App\Domains\Geography\Enums\SpatialZoneStatus;
use App\Domains\Geography\Enums\SpatialZoneType;
use App\Domains\Geography\Exceptions\SpatialException;
use App\Domains\Geography\Models\LegalRuleSpatialZone;
use App\Domains\Geography\Models\SpatialZone;
use App\Domains\Geography\Models\SpatialZoneGeometryVersion;
use App\Domains\Geography\Queries\FindZonesContainingPointQuery;
use App\Domains\Geography\Support\BoundingBox;
use App\Domains\Geography\Support\SpatialDriver;
use App\Domains\Geography\Support\SpatialLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class SpatialQueryService
{
    public function __construct(
        private readonly FindZonesContainingPointQuery $containing,
        private readonly SpatialPresenter $presenter,
        private readonly SpatialPublicCache $cache,
        private readonly SpatialLogger $logger,
        private readonly ZoneDisplayState $displayState,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function lookup(PointLookupQueryData $query, string $locale = 'ka'): array
    {
        $matches = $this->containing->execute($query);
        $payload = [];
        foreach ($matches as $match) {
            $payload[] = $this->presenter->zoneMatch($match['geometry'], $match['classification'], $locale, false);
        }

        return $payload;
    }

    /**
     * @param  list<string>|null  $types
     * @return array<string, mixed>
     */
    public function viewport(BoundingBox $bbox, Carbon $at, SpatialDetailLevel $detail, string $locale, ?array $types, int $page, int $perPage, ?string $activity = null): array
    {
        $maxSpan = $detail === SpatialDetailLevel::Full
            ? (float) config('spatial.query.max_viewport_full_span_degrees', 2.0)
            : (float) config('spatial.query.max_viewport_span_degrees', 8.0);
        if ($bbox->spanDegrees() > $maxSpan) {
            throw SpatialException::viewportRejected('The requested viewport is too large for this detail level.');
        }

        $params = [
            'bbox' => $bbox->toArray(),
            'at' => $at->toIso8601String(),
            'detail' => $detail->value,
            'locale' => $locale,
            'types' => $types,
            'activity' => $activity,
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($types === []) {
            return $this->emptyViewport($detail, false);
        }

        return $this->cache->remember('viewport', $params, function () use ($bbox, $at, $detail, $locale, $types, $page, $perPage, $activity): array {
            $max = (int) config('spatial.query.max_viewport_features', 100);
            $query = SpatialZoneGeometryVersion::query()
                ->with([
                    'zone.translations',
                    'zone.dataset.source',
                    'zone.assignments' => function ($builder) use ($at): void {
                        $builder->publishedEffective($at)->with('rule');
                    },
                ])
                ->publishedEffective($at)
                ->intersectingBbox($bbox)
                ->whereHas('zone', function ($builder) use ($types): void {
                    $builder->where('status', SpatialZoneStatus::Active);
                    if ($types !== null && $types !== []) {
                        $builder->whereIn('zone_type', $types);
                    }
                })
                ->orderBy('id');

            $this->selectDisplayGeometry($query, $detail);

            /** @var LengthAwarePaginator<int, SpatialZoneGeometryVersion> $paginator */
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            if ($paginator->total() > $max && $detail === SpatialDetailLevel::Full) {
                throw SpatialException::viewportRejected('Too many features for full-detail geometry. Choose a smaller viewport or a coarser detail level.');
            }

            $features = [];
            $includeGeometry = $detail !== SpatialDetailLevel::Country;
            foreach ($paginator->items() as $row) {
                $legalState = $this->displayState->summarize($row->zone->assignments, $activity);
                $features[] = $this->presenter->viewportFeature($row, $locale, $includeGeometry, $legalState);
            }

            $this->logger->info('viewport_query', [
                'feature_count' => count($features),
                'west' => $bbox->west->value,
                'south' => $bbox->south->value,
                'east' => $bbox->east->value,
                'north' => $bbox->north->value,
            ]);

            return [
                'type' => 'FeatureCollection',
                'crs' => ['type' => 'name', 'properties' => ['name' => 'EPSG:4326']],
                'coordinate_order' => 'longitude,latitude',
                'features' => $features,
                'generated_at' => now()->toIso8601String(),
                'detail' => $detail->value,
                'geometry_included' => $includeGeometry,
                'disclaimer' => (string) config('spatial.disclaimer_key'),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, string $locale, int $limit = 8): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $at = now();
        $zones = SpatialZone::query()
            ->with([
                'translations',
                'geometryVersions' => function ($builder) use ($at): void {
                    $builder->publishedEffective($at);
                },
            ])
            ->where('status', SpatialZoneStatus::Active)
            ->whereHas('geometryVersions', function ($builder) use ($at): void {
                $builder->publishedEffective($at);
            })
            ->where(function ($builder) use ($like): void {
                $builder->where('default_name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhereHas('translations', function ($translations) use ($like): void {
                        $translations->where('name', 'like', $like);
                    });
            })
            ->orderBy('default_name')
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($zones as $zone) {
            $geometry = $zone->geometryVersions->first();
            if ($geometry === null) {
                continue;
            }
            $results[] = [
                'result_type' => 'spatial_zone',
                'id' => $zone->public_id,
                'slug' => $zone->slug,
                'name' => $zone->localizedName($locale),
                'official_name' => $zone->default_name,
                'zone_type' => $zone->zone_type instanceof SpatialZoneType ? $zone->zone_type->value : (string) $zone->zone_type,
                'region_code' => $zone->region_code,
                'bbox' => [
                    $geometry->min_longitude,
                    $geometry->min_latitude,
                    $geometry->max_longitude,
                    $geometry->max_latitude,
                ],
            ];
        }

        return $results;
    }

    /**
     * Region and local zooms draw a simplified outline. Full detail keeps the
     * stored boundary. The native column is latitude-first, so the outline is
     * swapped back to longitude-first before it is drawn.
     *
     * @param  Builder<SpatialZoneGeometryVersion>  $query
     */
    private function selectDisplayGeometry(Builder $query, SpatialDetailLevel $detail): void
    {
        if (! SpatialDriver::supportsNativeGeometry() || $detail === SpatialDetailLevel::Full || $detail === SpatialDetailLevel::Country) {
            return;
        }

        $tolerance = $detail === SpatialDetailLevel::Local ? 0.002 : 0.008;
        $table = $query->getModel()->getTable();
        $query->select([
            "{$table}.id",
            "{$table}.public_id",
            "{$table}.spatial_zone_id",
            "{$table}.spatial_dataset_version_id",
            "{$table}.min_longitude",
            "{$table}.min_latitude",
            "{$table}.max_longitude",
            "{$table}.max_latitude",
            "{$table}.centroid_longitude",
            "{$table}.centroid_latitude",
            "{$table}.vertex_count",
            "{$table}.polygon_count",
            "{$table}.area_square_meters",
            "{$table}.geometry_checksum",
            "{$table}.source_feature_identifier",
            "{$table}.effective_from",
            "{$table}.effective_until",
            "{$table}.status",
            "{$table}.validation_status",
            "{$table}.validation_warnings",
            "{$table}.reviewed_at",
            "{$table}.reviewed_by",
            "{$table}.published_at",
            "{$table}.published_by",
            "{$table}.created_at",
            "{$table}.updated_at",
        ])->selectRaw(
            "COALESCE(NULLIF(ST_AsText(ST_Simplify(ST_SwapXY(ST_SRID({$table}.geometry, 0)), ?)), ''), {$table}.geometry_wkt) as geometry_wkt",
            [$tolerance],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyViewport(SpatialDetailLevel $detail, bool $includeGeometry): array
    {
        return [
            'type' => 'FeatureCollection',
            'crs' => ['type' => 'name', 'properties' => ['name' => 'EPSG:4326']],
            'coordinate_order' => 'longitude,latitude',
            'features' => [],
            'generated_at' => now()->toIso8601String(),
            'detail' => $detail->value,
            'geometry_included' => $includeGeometry,
            'disclaimer' => (string) config('spatial.disclaimer_key'),
            'pagination' => [
                'current_page' => 1,
                'per_page' => 0,
                'total' => 0,
                'last_page' => 1,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function zoneDetails(string $publicId, Carbon $at, string $locale, bool $includeGeometry): array
    {
        return $this->cache->remember('zone', [
            'id' => $publicId,
            'at' => $at->toIso8601String(),
            'locale' => $locale,
            'geometry' => $includeGeometry,
        ], function () use ($publicId, $at, $locale, $includeGeometry): array {
            $zone = SpatialZone::query()
                ->with(['translations', 'dataset.source'])
                ->where('public_id', $publicId)
                ->where('status', SpatialZoneStatus::Active)
                ->first() ?? throw SpatialException::notFound('Spatial zone');

            $geometry = SpatialZoneGeometryVersion::query()
                ->publishedEffective($at)
                ->where('spatial_zone_id', $zone->id)
                ->orderByDesc('id')
                ->first();

            $assignments = LegalRuleSpatialZone::query()
                ->with(['rule.citations.provision.version.document.source'])
                ->publishedEffective($at)
                ->where('spatial_zone_id', $zone->id)
                ->whereHas('rule', fn ($builder) => $builder->where('status', 'published'))
                ->orderByDesc('precedence')
                ->get();

            return $this->presenter->zonePublic($zone, $geometry, $assignments, $locale, $includeGeometry);
        });
    }
}
