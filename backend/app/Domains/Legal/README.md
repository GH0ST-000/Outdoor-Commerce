# Legal module

Day 25 owns official legal sources, immutable document versions, provisions, structured rules, conflict detection, and point-in-time evaluation.

Day 26 extends this module with season definitions, derived occurrences, overrides, and period availability. Day 27 adds location evaluation (`SpatialLegalEvaluator`) using Geography spatial queries. Biological species facts remain in Hunting. The public map remains Day 28. See [ADR 0019](../../../../docs/adr/0019-versioned-legal-rules.md), [ADR 0020](../../../../docs/adr/0020-season-calendar-projections.md), [ADR 0021](../../../../docs/adr/0021-mysql-spatial-zones.md), [docs/legal.md](../../../../docs/legal.md), [docs/legal-calendar.md](../../../../docs/legal-calendar.md), and [docs/spatial.md](../../../../docs/spatial.md).

## Rules

- MySQL is authoritative. Meilisearch is optional discovery only.
- Missing evidence yields `unknown`. Absence of a prohibition is not permission.
- Published rules require a primary citation to an approved provision on an approved version of a verified source.
- Source-change detection never publishes.
- Files live on the private `legal_private` disk. Originals are never public URLs.

## Public contracts

Enums, Models, Queries, Events, DTOs, Actions.

## May depend on

Shared; Identity users/permissions; Operations audit; Hunting `Species` model (nullable species scope).
