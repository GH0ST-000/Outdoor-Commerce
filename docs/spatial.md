# Spatial zones (Day 27)

Day 27 adds versioned, source-backed spatial zones in the Geography module, with location evaluation in Legal. The public interactive map is [Day 28](map.md).

This is **informational**. Absence of a prohibited zone is not permission. Missing spatial evidence returns `unknown`. Contradictory published assignments return `conflict`.

See [ADR 0021](adr/0021-mysql-spatial-zones.md), `backend/app/Domains/Geography/README.md`, and [legal calendar](legal-calendar.md).

## Patterns reused

- Modular domain `App\Domains\Geography` with public contracts: Enums, Models, Queries, DTOs.
- Legal owns `SpatialLegalEvaluator` and consumes Geography Queries/Models only.
- UUID `public_id`; `/api/v1` envelopes; pagination default 10.
- Sanctum + `EnsureHasPermission`; four-eyes publication via `spatial.versions.publish`.
- Private local disk `spatial_private` (not S3/MinIO).
- Redis cache version bump `spatial:public:version`. Never cache one coordinate as another.
- Pest tests; Vitest admin components.

## Coordinate policy

- Canonical SRID **4326**.
- API and GeoJSON order: **longitude, then latitude**.
- Valid ranges: longitude −180…180, latitude −90…90.
- Legal classification uses PHP point-in-polygon on canonical MULTIPOLYGON WKT (inside / outside / on_boundary / near).
- Points exactly on a ring are `on_boundary` (application epsilon, not a MySQL edge-case).
- Near-boundary uses **degrees**, never metres.

## MySQL spatial

Required in CI/production: **MySQL 8.4** with `POINT`, `POLYGON`, `MULTIPOLYGON`, SRID 4326, spatial indexes, `ST_GeomFromText`, `MBRContains` / `MBRIntersects`.

SQLite tests store WKT + numeric bounding-box columns only. SQLite is **not** proof of MySQL spatial correctness. PHP PIP remains the legal classifier on every driver. MySQL MBR is a candidate filter only.

Indexed numeric columns: `min_longitude`, `min_latitude`, `max_longitude`, `max_latitude`. Display simplification is deferred; viewport APIs use payload and span limits instead.

## Import

GeoJSON only. Zip, KML, and shapefile packages are rejected. Non-4326 CRS is rejected (no hand-rolled reprojection). Imports never publish.

## Public APIs for Day 28

- `GET /api/v1/spatial/zones?bbox=west,south,east,north`
- `GET /api/v1/spatial/zones/{publicId}`
- `GET /api/v1/spatial/lookup?lng=&lat=`
- `GET /api/v1/spatial/evaluate?lng=&lat=&activity=`

Admin conflict review: `GET /api/v1/admin/spatial/conflicts` (spatial-detected legal conflicts only).

## Privacy

Public evaluation does not persist user coordinates. Logs redact longitude/latitude.

## Operations

```bash
cd backend
php artisan migrate
php artisan queue:work --queue=default
```

Create `storage/app/private/spatial` if the disk root is missing. Import jobs use `SPATIAL_IMPORT_QUEUE` (default `default`). There is no scheduled spatial crawler.

## Testing

```bash
cd backend
composer test
PAO_DISABLE=true vendor/bin/pest --compact tests/Unit/Geography tests/Feature/Geography
```

Local Pest uses SQLite (WKT + bbox). CI `backend-tests` uses MySQL 8.4 and exercises MBR candidate filters. PHP PIP remains the legal classifier on both.

```bash
cd frontend
npm run test:run
npm run typecheck
```

## Day 28 contract

Public APIs return GeoJSON FeatureCollections for viewports, zone details with attribution and verification timestamps, lookup (zones only), and evaluate (legal conclusion). Coordinate order is documented as longitude, latitude. Draft, rejected, and superseded unpublished geometry is omitted. Do not treat the map renderer as a legal engine.

## Demo data

Tests use fictional `XX` jurisdictions and names prefixed `FICTIONAL`. Production seeders must not create Georgian legal boundaries.

Official APA17, APA29, MEPA51, and MEPA52 layers are registered as draft datasets without geometry. Export and import steps are in [official Georgian data](official-georgia.md). `php artisan spatial:import` keeps uploaded GeoJSON in draft. A missing file does not become an empty permitted map.
