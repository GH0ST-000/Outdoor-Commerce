# Geography module

Day 27 owns versioned spatial sources, datasets, zones, canonical MULTIPOLYGON geometry (SRID 4326), GeoJSON import, and spatial queries.

Legal interpretation of hunting or fishing permission stays in Legal (`SpatialLegalEvaluator`). The public interactive map is Day 28.

See [ADR 0021](../../../../docs/adr/0021-mysql-spatial-zones.md) and [docs/spatial.md](../../../../docs/spatial.md).

## Rules

- MySQL is authoritative. Redis is a cache only.
- Canonical geometry is MULTIPOLYGON SRID 4326, longitude then latitude.
- Published geometry versions are immutable.
- Display geometry must never replace canonical geometry for evaluation (simplification is deferred).
- Files live on the private `spatial_private` disk.
- Missing spatial evidence is not permission.

## Public contracts

Enums, Models, Queries, DTOs.

`FindZonesContainingPointQuery` is the approved lookup entry point for Legal.

## May depend on

Shared; Legal Models/Enums (source and rule foreign keys).

## Explicitly outside this module

Legal conclusions; product ranking; the public interactive map.
