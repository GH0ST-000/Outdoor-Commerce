# Hunting module

## Responsibility

Species, seasons, limits, and legal source material for hunting rules.

## Data owned

Species, seasons, bag limits, legal citations.

## Public contracts

Legal context contracts consumed by Catalog/Recommendations carefully.

Classes under `Contracts/`, and any Action/Query/DTO explicitly listed here as public, are the only approved entry points for other modules.

## Events this module may publish

HuntingRulePublished (example for later).

## May depend on

Shared; Geography for zone references via contracts.

## Explicitly outside this module

Polygon geometry storage and product ranking.

## Structure

Follow the standard module layout documented in `docs/architecture.md` when implementing features. Day 2 ships boundaries only—no business behavior yet.
