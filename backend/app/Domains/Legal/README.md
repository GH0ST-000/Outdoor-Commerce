# Legal module

Day 25 owns official legal sources, immutable document versions, provisions, structured rules, conflict detection, and point-in-time evaluation.

This module does **not** own species biology, calendars, maps, or product recommendations. See [ADR 0019](../../../../docs/adr/0019-versioned-legal-rules.md) and [docs/legal.md](../../../../docs/legal.md).

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
