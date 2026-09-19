# Geography module

## Responsibility

Zones, polygons, and spatial queries.

## Data owned

Geographic zones and spatial indexes.

## Public contracts

Spatial lookup contracts for Hunting/Shipping.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

ZoneUpdated (example for later).

## May depend on

Shared.

## Explicitly outside this module

Legal interpretation of hunting permission; recommendation ranking.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
