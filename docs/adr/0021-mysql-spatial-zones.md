# ADR 0021 — MySQL spatial zones with SRID 4326

## Status

Accepted (Day 27)

## Context

The platform must represent protected areas and legal zones, answer point-in-polygon questions, and remain on MySQL as the source of truth. PostgreSQL/PostGIS is out of scope. SQLite is used for some local tests and lacks MySQL spatial types.

## Decision

1. Keep MySQL 8.4 as the spatial database. Use native `GEOMETRY` / `MULTIPOLYGON` SRID 4326 plus a spatial index in MySQL.
2. Always persist canonical WKT and indexed numeric bounding-box columns so SQLite and MySQL share the same legal model.
3. Treat PHP ray-casting (with holes) as the **legal** classifier: inside, outside, on_boundary, near-boundary. Use MySQL `MBRContains` / `MBRIntersects` only to reduce candidates.
4. Canonical coordinate order is longitude then latitude, matching GeoJSON and WKT.
5. Keep display geometry separate from canonical geometry. Day 27 defers simplification and applies viewport/payload limits instead.
6. Published dataset and geometry versions are immutable; a changed checksum or boundary creates a new version.
7. Geography owns polygons and queries. Legal owns location conclusions via `SpatialLegalEvaluator`.
8. No matching prohibited zone does not mean allowed. Near-boundary results must not be overconfident. Exact user coordinates are not stored by default.

## Consequences

- CI must run feature tests on MySQL 8.4 to exercise MBR filters.
- Local SQLite tests still prove PHP PIP, import, publication, and API contracts.
- Reprojection is not implemented; non-4326 uploads are rejected until a verified projection library is added.
- Day 28 can consume stable public spatial APIs without treating the map renderer as a legal engine.
